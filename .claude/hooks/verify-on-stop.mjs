#!/usr/bin/env node
// Hook Stop : avant que Claude rende la main, vérifie les parties du monorepo qui ont changé.
//   backend/  modifié -> php bin/phpunit
//   frontend/ modifié -> npm run lint, puis npm run build
// En cas d'échec, la fin est bloquée et la sortie de la commande est renvoyée à Claude.
//
// « Modifié » = l'empreinte du dossier (chemin, taille, date de chaque fichier suivi ou non
// ignoré par git) diffère de celle de la dernière vérification réussie, conservée dans
// .claude/hooks/.verify-on-stop-state.json (ignoré par git).

import { spawnSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { existsSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..')
const STATE_FILE = join(ROOT, '.claude', 'hooks', '.verify-on-stop-state.json')
// Au-delà de ce nombre de blocages consécutifs, on laisse finir (avec un avertissement)
// plutôt que de boucler indéfiniment sur un échec que Claude n'arrive pas à corriger.
const MAX_CONSECUTIVE_BLOCKS = 3
const OUTPUT_TAIL_CHARS = 4000

const TARGETS = [
  {
    dir: 'backend',
    steps: [{ label: 'php bin/phpunit', cmd: 'php', args: ['bin/phpunit'] }],
  },
  {
    dir: 'frontend',
    steps: [
      { label: 'npm run lint', cmd: 'npm', args: ['run', 'lint'] },
      { label: 'npm run build', cmd: 'npm', args: ['run', 'build'] },
    ],
  },
]

function readStdin() {
  try {
    return JSON.parse(readFileSync(0, 'utf8') || '{}')
  } catch {
    return {}
  }
}

function loadState() {
  try {
    return JSON.parse(readFileSync(STATE_FILE, 'utf8'))
  } catch {
    return { fingerprints: {}, consecutiveBlocks: 0 }
  }
}

function saveState(state) {
  writeFileSync(STATE_FILE, JSON.stringify(state, null, 2))
}

function fingerprint(dir) {
  const result = spawnSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard', '--', dir], {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  })
  if (result.status !== 0) {
    return null
  }

  const hash = createHash('sha256')
  for (const file of result.stdout.split('\0').filter(Boolean).sort()) {
    try {
      const { size, mtimeMs } = statSync(join(ROOT, file))
      hash.update(`${file}\0${size}\0${mtimeMs}\n`)
    } catch {
      hash.update(`${file}\0deleted\n`)
    }
  }
  return hash.digest('hex')
}

function run(step, cwd) {
  // shell: true pour trouver npm.cmd / php.exe sous Windows
  const result = spawnSync([step.cmd, ...step.args].join(' '), {
    cwd,
    shell: true,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
    env: { ...process.env, CI: 'true', FORCE_COLOR: '0', NO_COLOR: '1' },
  })
  const output = `${result.stdout ?? ''}${result.stderr ?? ''}`.trim()
  return { ok: result.status === 0, output: output.slice(-OUTPUT_TAIL_CHARS) }
}

const input = readStdin()
const state = loadState()
const failures = []
const verified = []

for (const target of TARGETS) {
  const cwd = join(ROOT, target.dir)
  if (!existsSync(cwd)) {
    continue
  }

  const current = fingerprint(target.dir)
  if (current !== null && state.fingerprints[target.dir] === current) {
    continue
  }

  let ok = true
  for (const step of target.steps) {
    const result = run(step, cwd)
    if (!result.ok) {
      failures.push(`### ${target.dir} : \`${step.label}\` a échoué\n\n${result.output}`)
      ok = false
      break
    }
  }

  if (ok) {
    verified.push(target.dir)
    // Empreinte reprise après les commandes : un build ou un cache régénéré ne compte pas comme un changement
    state.fingerprints[target.dir] = fingerprint(target.dir) ?? current
  }
}

if (failures.length === 0) {
  state.consecutiveBlocks = 0
  saveState(state)
  if (verified.length > 0) {
    console.log(JSON.stringify({ systemMessage: `Vérifications OK : ${verified.join(', ')}` }))
  }
  process.exit(0)
}

if (input.stop_hook_active && state.consecutiveBlocks >= MAX_CONSECUTIVE_BLOCKS) {
  state.consecutiveBlocks = 0
  saveState(state)
  console.log(JSON.stringify({
    systemMessage: `Vérifications toujours en échec après ${MAX_CONSECUTIVE_BLOCKS} tentatives : fin autorisée, à corriger manuellement.\n\n${failures.join('\n\n')}`,
  }))
  process.exit(0)
}

state.consecutiveBlocks = input.stop_hook_active ? state.consecutiveBlocks + 1 : 1
saveState(state)
console.log(JSON.stringify({
  decision: 'block',
  reason: `Les vérifications du hook Stop ont échoué. Corrige le problème avant de terminer.\n\n${failures.join('\n\n')}`,
}))
process.exit(0)
