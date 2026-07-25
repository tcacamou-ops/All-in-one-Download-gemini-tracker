#!/bin/bash

# Script pour synchroniser la branche trunk avec main dans ce dépôt.
# Supprime trunk, la recrée depuis main, force-pousse, puis nettoie localement.

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}  [INFO]${NC}    $1"; }
log_success() { echo -e "${GREEN}  [OK]${NC}      $1"; }
log_warning() { echo -e "${YELLOW}  [WARN]${NC}    $1"; }
log_error()   { echo -e "${RED}  [ERROR]${NC}   $1"; }
log_step()    { echo -e "${CYAN}  [STEP]${NC}    $1"; }

separator() {
    echo -e "${BOLD}──────────────────────────────────────────────────${NC}"
}

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
dir_name="$(basename "$PLUGIN_DIR")"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         SYNC TRUNK → MAIN  (force push)          ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
log_info "Plugin : $dir_name"
log_info "Répertoire : $PLUGIN_DIR"
echo ""

if [[ ! -d "$PLUGIN_DIR/.git" ]]; then
    log_error "Pas un dépôt Git — abandon"
    exit 1
fi

cd "$PLUGIN_DIR"

separator

# ── 1. S'assurer qu'on est sur main ───────────────────────────────────
log_step "Basculement sur main…"
if ! git checkout main --quiet 2>/dev/null; then
    log_error "Impossible de basculer sur main — abandon"
    exit 1
fi
log_success "Sur la branche main"

# ── 2. Supprimer trunk locale si elle existe ───────────────────────────
log_step "Suppression de la branche locale trunk (si elle existe)…"
if git show-ref --verify --quiet refs/heads/trunk; then
    if git branch -D trunk --quiet 2>/dev/null; then
        log_success "Branche locale trunk supprimée"
    else
        log_error "Échec de la suppression de trunk — abandon"
        exit 1
    fi
else
    log_info "Pas de branche locale trunk à supprimer"
fi

# ── 3. Créer trunk depuis main ─────────────────────────────────────────
log_step "Création de la branche trunk depuis main…"
if git checkout -b trunk --quiet 2>/dev/null; then
    log_success "Branche trunk créée depuis main"
else
    log_error "Échec de la création de trunk — abandon"
    exit 1
fi

# ── 4. Force-push de trunk ─────────────────────────────────────────────
log_step "Force-push de trunk vers origin…"
push_output=$(git push --force origin trunk 2>&1)
push_status=$?
if [[ $push_status -eq 0 ]]; then
    log_success "Force-push réussi"
else
    log_error "Échec du force-push : $push_output"
    # On tente quand même de nettoyer localement
fi

# ── 5. Revenir sur main ────────────────────────────────────────────────
log_step "Retour sur main…"
if git checkout main --quiet 2>/dev/null; then
    log_success "De retour sur main"
else
    log_error "Impossible de revenir sur main"
    exit 1
fi

# ── 6. Supprimer trunk locale ──────────────────────────────────────────
log_step "Suppression de la branche locale trunk…"
if git branch -D trunk --quiet 2>/dev/null; then
    log_success "Branche locale trunk supprimée"
else
    log_warning "Impossible de supprimer la branche locale trunk"
fi

echo ""
separator
if [[ $push_status -eq 0 ]]; then
    log_success "✓  $dir_name — synchronisation terminée"
    exit 0
else
    log_error "✗  $dir_name — push échoué (voir ci-dessus)"
    exit 1
fi
