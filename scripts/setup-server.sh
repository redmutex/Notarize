#!/usr/bin/env bash
# ============================================================
# Notarize — One-time server setup
# Run as root on s01.riteclouds.com
# ============================================================
set -euo pipefail

APP_DIR=/var/www/notarize
UPLOAD_DIR=$APP_DIR/uploads
KEY_DIR=/etc/notarize/keys
DB_NAME=notarize_app
DB_USER=notarize_app
DOMAIN=notarize.onrite.cloud
PHP_SOCK=/run/php/php8.3-fpm.sock

# ── 1. Directories ────────────────────────────────────────────
echo "[1/7] Creating directories…"
mkdir -p "$APP_DIR" "$UPLOAD_DIR" "$KEY_DIR"
chown -R robot:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$UPLOAD_DIR"

# ── 2. RSA key pair ───────────────────────────────────────────
if [ ! -f "$KEY_DIR/private.pem" ]; then
    echo "[2/7] Generating RSA-4096 signing key pair…"
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$KEY_DIR/private.pem"
    openssl rsa -pubout -in "$KEY_DIR/private.pem" -out "$KEY_DIR/public.pem"
    chmod 640 "$KEY_DIR/private.pem"
    chmod 644 "$KEY_DIR/public.pem"
    chown www-data:www-data "$KEY_DIR/private.pem" "$KEY_DIR/public.pem"
    echo "    Keys written to $KEY_DIR"
else
    echo "[2/7] RSA keys already exist — skipping"
fi

# ── 3. MySQL database & user ──────────────────────────────────
echo "[3/7] Setting up MySQL…"
read -rsp "MySQL root password: " MYSQL_ROOT_PASS
echo

DB_PASS=$(openssl rand -base64 24 | tr -d '=+/' | head -c 24)
MYSQL_PWD="$MYSQL_ROOT_PASS" mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "    Database: $DB_NAME"
echo "    User:     $DB_USER"
echo "    Password: $DB_PASS   <--- ADD THIS TO CREDENTIALS FILE & GITHUB SECRET"

# ── 4. Nginx vhost ────────────────────────────────────────────
echo "[4/7] Writing nginx vhost…"
VHOST=/etc/nginx/sites-available/$DOMAIN.conf
cat > "$VHOST" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name $DOMAIN;

    root  $APP_DIR/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    client_max_body_size 12M;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php\$ {
        fastcgi_pass  unix:$PHP_SOCK;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include       fastcgi_params;
    }

    # Block direct access to sensitive paths
    location ~ /\.(env|git) {
        deny all;
        return 404;
    }

    # Uploads are outside public/ but protect just in case
    location /uploads {
        deny all;
        return 404;
    }
}
NGINX

ln -sf "$VHOST" /etc/nginx/sites-enabled/$DOMAIN.conf
nginx -t
echo "    Vhost written and linked"

# ── 5. SSL certificate ────────────────────────────────────────
echo "[5/7] Issuing SSL certificate via certbot…"
certbot certonly --nginx -d "$DOMAIN" --non-interactive --agree-tos -m danial@redmutex.com
systemctl reload nginx
echo "    SSL issued"

# ── 6. robot user & permissions ───────────────────────────────
echo "[6/7] Adding robot to www-data group…"
usermod -aG www-data robot

# ── 7. Summary ────────────────────────────────────────────────
echo ""
echo "========================================================"
echo "  Setup complete!"
echo "========================================================"
echo ""
echo "  App directory : $APP_DIR"
echo "  Upload dir    : $UPLOAD_DIR"
echo "  Key dir       : $KEY_DIR"
echo "  Nginx vhost   : $VHOST"
echo "  DB name       : $DB_NAME"
echo "  DB user       : $DB_USER"
echo "  DB password   : $DB_PASS"
echo ""
echo "  Next steps:"
echo "  1. Add DB_PASSWORD=$DB_PASS to GitHub secret"
echo "  2. Add SSH_PRIVATE_KEY (robot's key) to GitHub secret"
echo "  3. Add SSH_HOST=s01.riteclouds.com  to GitHub var"
echo "  4. Add SSH_USER=robot               to GitHub var"
echo "  5. Push to main branch to trigger deploy"
echo ""
echo "  Update $HOME/Google\\ Drive/My\\ Drive/Sensitive/credentials-mix/s01-riteclouds-access.txt"
echo "========================================================"
