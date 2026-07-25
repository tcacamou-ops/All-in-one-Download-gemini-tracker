#!/bin/bash

# Script pour créer et pousser le tag git de ce plugin, à partir de la
# version courante (Stable tag) déjà présente dans readme.txt.
# Ne modifie aucun fichier et ne fait aucun commit — voir release-bump-version.sh pour ça.
# Par défaut : mode dry-run (aucune écriture). Utiliser --apply pour exécuter réellement.

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
log_dry()     { echo -e "${YELLOW}  [DRY-RUN]${NC} $1"; }

separator() {
    echo -e "${BOLD}──────────────────────────────────────────────────${NC}"
}

# ── Options ──────────────────────────────────────────────────────────────
APPLY=false
for arg in "$@"; do
    case "$arg" in
        --apply)
            APPLY=true
            ;;
        --help|-h)
            echo "Usage: $0 [--apply]"
            echo ""
            echo "  Sans option : mode dry-run (par défaut). N'exécute aucune commande git."
            echo "  --apply     : crée réellement le tag et le pousse avec la branche courante."
            echo ""
            echo "Suppose que readme.txt contient déjà la version à taguer (voir release-bump-version.sh)."
            exit 0
            ;;
        *)
            log_warning "Option inconnue ignorée : $arg"
            ;;
    esac
done

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
dir_name="$(basename "$PLUGIN_DIR")"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         RELEASE — TAG + PUSH                      ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
log_info "Plugin : $dir_name"
log_info "Répertoire : $PLUGIN_DIR"
if [[ "$APPLY" == true ]]; then
    log_warning "Mode APPLY activé — un tag sera créé et poussé sur git."
else
    log_info "Mode DRY-RUN (par défaut) — aucune commande git ne sera exécutée. Utilisez --apply pour exécuter réellement."
fi
echo ""

separator

if [[ ! -f "$PLUGIN_DIR/readme.txt" ]]; then
    log_error "readme.txt absent — abandon"
    exit 1
fi

current_version=$(grep -i "^Stable tag:" "$PLUGIN_DIR/readme.txt" | sed 's/^Stable tag:[[:space:]]*//' | tr -d '[:space:]')
if [[ -z "$current_version" ]]; then
    log_error "Stable tag introuvable dans readme.txt — abandon"
    exit 1
fi
new_tag="v${current_version}"

log_info "Version à tagger : $current_version → tag $new_tag"

# Vérifications git
if ! git -C "$PLUGIN_DIR" rev-parse --git-dir &>/dev/null; then
    log_error "Pas un repo git — pas de tag/push possible, abandon"
    exit 1
fi

if ! git -C "$PLUGIN_DIR" remote | grep -q .; then
    log_error "Pas de remote git — pas de push possible, abandon"
    exit 1
fi

if git -C "$PLUGIN_DIR" tag | grep -qx "$new_tag"; then
    log_error "Le tag $new_tag existe déjà localement — abandon"
    exit 1
fi

current_branch=$(git -C "$PLUGIN_DIR" rev-parse --abbrev-ref HEAD)

if [[ "$APPLY" != true ]]; then
    log_dry "git -C \"$PLUGIN_DIR\" tag \"$new_tag\""
    log_dry "git -C \"$PLUGIN_DIR\" push origin \"$current_branch\""
    log_dry "git -C \"$PLUGIN_DIR\" push origin \"$new_tag\""
    log_success "Simulation terminée pour $dir_name (rien n'a été modifié)"
    echo ""
    log_warning "Mode DRY-RUN : aucune modification n'a été effectuée. Relancez avec --apply pour appliquer réellement."
    exit 0
fi

# ── Exécution réelle (--apply) ──────────────────────────────────────
log_step "Création du tag $new_tag…"
if git -C "$PLUGIN_DIR" tag "$new_tag"; then
    log_success "Tag créé"
else
    log_error "Échec de la création du tag"
    exit 1
fi

log_step "Push de la branche $current_branch et du tag $new_tag…"
if git -C "$PLUGIN_DIR" push origin "$current_branch" && git -C "$PLUGIN_DIR" push origin "$new_tag"; then
    log_success "Push réussi"
else
    log_error "Échec du push"
    exit 1
fi

echo ""
separator
log_success "Release $new_tag terminée pour $dir_name"
