#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

say() {
  printf '\n==> %s\n' "$1"
}

prompt() {
  local label="$1"
  local default="${2:-}"
  local value

  if [ -n "$default" ]; then
    read -r -p "${label} [${default}]: " value
    printf '%s' "${value:-$default}"
  else
    read -r -p "${label}: " value
    printf '%s' "$value"
  fi
}

confirm() {
  local label="$1"
  local default="${2:-n}"
  local value
  local hint="[y/N]"

  if [ "$default" = "y" ]; then
    hint="[Y/n]"
  fi

  read -r -p "${label} ${hint}: " value
  value="${value:-$default}"
  case "$value" in
    y|Y|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

require_sudo() {
  if ! command -v sudo >/dev/null 2>&1; then
    printf 'sudo is required for VPS bootstrap.\n' >&2
    exit 1
  fi

  sudo -v
}

require_sudo

PROJECT_PATH="$(prompt "Project path on VPS" "$ROOT_DIR")"
DEPLOY_USER="$(prompt "Deploy user" "deploy")"
WEB_GROUP="$(prompt "Web server group" "www-data")"
PHP_FPM_SERVICE="$(prompt "PHP-FPM service name" "php8.1-fpm")"
APACHE_SERVICE="$(prompt "Apache service name" "apache2")"
BACKUP_DIR="$(prompt "Database backup directory" "/var/backups/elsesserandco")"

say "Bootstrap summary"
printf '  project: %s\n' "$PROJECT_PATH"
printf '  deploy user: %s\n' "$DEPLOY_USER"
printf '  web group: %s\n' "$WEB_GROUP"
printf '  php-fpm: %s\n' "$PHP_FPM_SERVICE"
printf '  apache: %s\n' "$APACHE_SERVICE"
printf '  backups: %s\n' "$BACKUP_DIR"

if confirm "Install/upgrade required apt packages?" "n"; then
  say "Installing packages"
  sudo apt-get update
  sudo apt-get install -y \
    git curl ca-certificates gzip \
    apache2 mysql-client \
    php8.1-cli php8.1-fpm php8.1-mysql
fi

if command -v a2enmod >/dev/null 2>&1 && confirm "Enable required Apache modules?" "y"; then
  say "Enabling Apache modules"
  sudo a2enmod rewrite headers deflate expires proxy_fcgi setenvif
  sudo systemctl reload "$APACHE_SERVICE"
fi

if id "$DEPLOY_USER" >/dev/null 2>&1; then
  printf 'Deploy user already exists: %s\n' "$DEPLOY_USER"
else
  if confirm "Create deploy user ${DEPLOY_USER}?" "y"; then
    sudo adduser --disabled-password --gecos "" "$DEPLOY_USER"
  fi
fi

if id "$DEPLOY_USER" >/dev/null 2>&1; then
  if confirm "Add ${DEPLOY_USER} to ${WEB_GROUP} group?" "y"; then
    sudo usermod -aG "$WEB_GROUP" "$DEPLOY_USER"
  fi
fi

if confirm "Fix project ownership and writable permissions?" "y"; then
  say "Fixing project permissions"
  sudo chown -R "${DEPLOY_USER}:${WEB_GROUP}" "$PROJECT_PATH"
  find "$PROJECT_PATH" -type d -not -path '*/.git*' -exec sudo chmod 775 {} \;
  find "$PROJECT_PATH" -type f -not -path '*/.git*' -exec sudo chmod 664 {} \;
  sudo chmod 600 "$PROJECT_PATH/.env" 2>/dev/null || true
  for dir in logs cache session uploads images/uploads; do
    sudo mkdir -p "$PROJECT_PATH/$dir"
    sudo chown -R "${DEPLOY_USER}:${WEB_GROUP}" "$PROJECT_PATH/$dir"
    sudo chmod -R 775 "$PROJECT_PATH/$dir"
  done
fi

if confirm "Create GitHub Actions SSH key for ${DEPLOY_USER}?" "n"; then
  say "Creating deploy SSH key"
  sudo -u "$DEPLOY_USER" mkdir -p "/home/${DEPLOY_USER}/.ssh"

  if [ -f "/home/${DEPLOY_USER}/.ssh/github_actions" ]; then
    printf 'Key already exists: /home/%s/.ssh/github_actions\n' "$DEPLOY_USER"
  else
    sudo -u "$DEPLOY_USER" ssh-keygen -t ed25519 -f "/home/${DEPLOY_USER}/.ssh/github_actions" -N ""
  fi

  sudo -u "$DEPLOY_USER" bash -c "cat '/home/${DEPLOY_USER}/.ssh/github_actions.pub' >> '/home/${DEPLOY_USER}/.ssh/authorized_keys'"
  sudo -u "$DEPLOY_USER" chmod 700 "/home/${DEPLOY_USER}/.ssh"
  sudo -u "$DEPLOY_USER" chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"

  printf '\nCopy this private key into GitHub secret VPS_SSH_KEY:\n'
  sudo cat "/home/${DEPLOY_USER}/.ssh/github_actions"
fi

if confirm "Write sudoers rules for deploy workflow?" "n"; then
  say "Writing sudoers"
  SUDOERS_FILE="/etc/sudoers.d/${DEPLOY_USER}-elsesserandco"
  TMP_FILE="$(mktemp)"

  cat > "$TMP_FILE" <<EOF
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload ${PHP_FPM_SERVICE}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload ${APACHE_SERVICE}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl is-active ${PHP_FPM_SERVICE}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/mkdir -p ${BACKUP_DIR}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/chown ${DEPLOY_USER}\\:${DEPLOY_USER} ${BACKUP_DIR}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/chown -R ${DEPLOY_USER}\\:${WEB_GROUP} ${PROJECT_PATH}
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/chmod -R 775 ${PROJECT_PATH}/*
EOF

  sudo visudo -cf "$TMP_FILE"
  sudo install -m 440 "$TMP_FILE" "$SUDOERS_FILE"
  rm -f "$TMP_FILE"
  printf 'Sudoers installed: %s\n' "$SUDOERS_FILE"
fi

if [ ! -f "$PROJECT_PATH/.env" ] && confirm "Run scripts/setup.sh to create .env and prepare database?" "y"; then
  say "Running interactive setup"
  cd "$PROJECT_PATH"
  bash scripts/setup.sh
elif [ -f "$PROJECT_PATH/.env" ]; then
  printf '.env already exists at %s/.env\n' "$PROJECT_PATH"
fi

say "GitHub Actions secrets to configure"
printf '  VPS_HOST=<server ip or domain>\n'
printf '  VPS_USER=%s\n' "$DEPLOY_USER"
printf '  VPS_PORT=22\n'
printf '  VPS_SSH_KEY=<private key printed above>\n'
printf '  VPS_PATH=%s\n' "$PROJECT_PATH"
printf '  SMOKE_URL=<public domain, optional>\n'

say "Bootstrap complete"
