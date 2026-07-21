#!/bin/bash

# Script pour incrémenter la version (patch +0.0.1) de ce plugin,
# mettre à jour readme.txt + le fichier principal, committer, tagger et pousser.
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
            echo "  Sans option : mode dry-run (par défaut). N'écrit rien, n'exécute aucune commande git."
            echo "  --apply     : effectue réellement les changements (fichiers + commit + tag + push)."
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
echo -e "${BOLD}║         RELEASE — BUMP VERSION + TAG             ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
log_info "Plugin : $dir_name"
log_info "Répertoire : $PLUGIN_DIR"
if [[ "$APPLY" == true ]]; then
    log_warning "Mode APPLY activé — les fichiers seront modifiés et poussés sur git."
else
    log_info "Mode DRY-RUN (par défaut) — aucune modification ne sera effectuée. Utilisez --apply pour exécuter réellement."
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

# Localiser le fichier principal du plugin (celui portant l'en-tête "Plugin Name:")
main_file=""
for php_file in "$PLUGIN_DIR"/*.php; do
    [[ -f "$php_file" ]] || continue
    if grep -q "Plugin Name:" "$php_file"; then
        main_file="$php_file"
        break
    fi
done

if [[ -z "$main_file" ]]; then
    log_error "Aucun fichier principal (en-tête 'Plugin Name:') trouvé — abandon"
    exit 1
fi

header_version=$(grep -i "^[[:space:]]*\*[[:space:]]*Version:" "$main_file" | sed 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '[:space:]')
if [[ -z "$header_version" ]]; then
    log_error "Version introuvable dans $(basename "$main_file") — abandon"
    exit 1
fi

if [[ "$header_version" != "$current_version" ]]; then
    log_warning "Divergence de version : readme.txt=$current_version vs $(basename "$main_file")=$header_version — utilisation de $current_version comme référence"
fi

# Calcul de la nouvelle version (incrément du dernier segment)
IFS='.' read -r major minor patch <<< "$current_version"
if [[ -z "$major" || -z "$minor" || -z "$patch" ]]; then
    log_error "Format de version inattendu ($current_version) — abandon"
    exit 1
fi
new_version="${major}.${minor}.$((patch + 1))"
new_tag="v${new_version}"

log_info "Version actuelle : $current_version  →  Nouvelle version : $new_version"
log_info "readme.txt         : Stable tag: $current_version  →  Stable tag: $new_version"
log_info "$(basename "$main_file") : Version: $header_version  →  Version: $new_version"

# Vérifications git
if ! git -C "$PLUGIN_DIR" rev-parse --git-dir &>/dev/null; then
    log_error "Pas un repo git — pas de tag/commit/push possible, abandon"
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

if [[ "$APPLY" != true ]]; then
    log_dry "sed -i 's/^Stable tag:.*/Stable tag: $new_version/' \"$PLUGIN_DIR/readme.txt\""
    log_dry "sed -i mise à jour de 'Version: $header_version' → 'Version: $new_version' dans \"$main_file\""
    log_dry "git -C \"$PLUGIN_DIR\" add readme.txt $(basename "$main_file")"
    log_dry "git -C \"$PLUGIN_DIR\" commit -m \"chore: bump version to $new_version\""
    log_dry "git -C \"$PLUGIN_DIR\" tag \"$new_tag\""
    log_dry "git -C \"$PLUGIN_DIR\" push origin <branche-courante>"
    log_dry "git -C \"$PLUGIN_DIR\" push origin \"$new_tag\""
    log_success "Simulation terminée pour $dir_name (rien n'a été modifié)"
    echo ""
    log_warning "Mode DRY-RUN : aucune modification n'a été effectuée. Relancez avec --apply pour appliquer réellement."
    exit 0
fi

# ── Exécution réelle (--apply) ──────────────────────────────────────
log_step "Mise à jour de readme.txt…"
sed -i "s/^Stable tag:.*/Stable tag: $new_version/" "$PLUGIN_DIR/readme.txt"
log_success "readme.txt mis à jour"

log_step "Mise à jour de $(basename "$main_file")…"
sed -i "s/^\([[:space:]]*\*[[:space:]]*Version:[[:space:]]*\).*/\1$new_version/" "$main_file"
log_success "$(basename "$main_file") mis à jour"

current_branch=$(git -C "$PLUGIN_DIR" rev-parse --abbrev-ref HEAD)

log_step "Commit des changements…"
if git -C "$PLUGIN_DIR" add readme.txt "$(basename "$main_file")" && \
   git -C "$PLUGIN_DIR" commit -m "chore: bump version to $new_version"; then
    log_success "Commit créé"
else
    log_error "Échec du commit"
    exit 1
fi

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
