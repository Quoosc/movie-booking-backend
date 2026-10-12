#!/usr/bin/env bash
set -euo pipefail
sudo -n true
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io docker-compose-v2 unzip
sudo systemctl enable --now docker
sudo usermod -aG docker ubuntu

# Dedicated demo swap file; leave existing swap and files unchanged.
if ! swapon --show=NAME --noheadings | grep -q .; then
    if [[ -e /var/swap/cineon.swap ]]; then
        echo 'Existing swap file requires manual inspection; refusing to overwrite it.'
        exit 1
    fi
    sudo install -d -m 700 /var/swap
    sudo fallocate -l 2G /var/swap/cineon.swap
    sudo chmod 600 /var/swap/cineon.swap
    sudo mkswap /var/swap/cineon.swap
    sudo swapon /var/swap/cineon.swap
    printf '%s\n' '/var/swap/cineon.swap none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null
fi
mkdir -p /home/ubuntu/cineon
chmod 700 /home/ubuntu/cineon

# Oracle Ubuntu images can reject HTTP even when OCI ingress rules allow it.
if sudo iptables -S INPUT | grep -q -- '-j REJECT'; then
    for port in 80 443; do
        if ! sudo iptables -C INPUT -p tcp --dport "$port" -j ACCEPT 2>/dev/null; then
            sudo iptables -I INPUT 1 -p tcp --dport "$port" -j ACCEPT
        fi
    done
    if command -v netfilter-persistent >/dev/null; then
        sudo netfilter-persistent save
    fi
fi
docker --version
free -h
