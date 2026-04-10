#!/bin/sh
# Generate self-signed TLS certificate for offline/internal network use
# Run this script once before starting the nginx container

SSL_DIR="$(dirname "$0")/ssl"
mkdir -p "$SSL_DIR"

if [ -f "$SSL_DIR/server.crt" ] && [ -f "$SSL_DIR/server.key" ]; then
    echo "TLS certificates already exist at $SSL_DIR"
    echo "Delete them first if you want to regenerate."
    exit 0
fi

openssl req -x509 -nodes -days 3650 \
    -newkey rsa:2048 \
    -keyout "$SSL_DIR/server.key" \
    -out "$SSL_DIR/server.crt" \
    -subj "/C=US/ST=Internal/L=ResearchHub/O=ResearchHub/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

chmod 600 "$SSL_DIR/server.key"
chmod 644 "$SSL_DIR/server.crt"

echo "Self-signed TLS certificate generated at $SSL_DIR"
echo "To trust this certificate on client machines:"
echo "  macOS:  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain $SSL_DIR/server.crt"
echo "  Linux:  sudo cp $SSL_DIR/server.crt /usr/local/share/ca-certificates/ && sudo update-ca-certificates"
echo "  Windows: Import $SSL_DIR/server.crt into Trusted Root Certification Authorities"
