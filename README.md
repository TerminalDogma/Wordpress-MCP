# WordPress with Advanced Custom Fields

A Docker Compose installation of **WordPress 7.1.4**, MariaDB, and **Advanced Custom Fields Free 6.8.10**. This repository contains the configuration and setup automation; a running site needs Docker or a PHP/database hosting service. GitHub Pages cannot run this installation.

## Start locally

Requires Docker Engine with Compose v2, Python 3, and a POSIX shell. On macOS or Windows, use Docker Desktop (and WSL or a compatible shell on Windows).

```sh
git clone https://github.com/TerminalDogma/Wordpress-MCP.git
cd Wordpress-MCP
python3 bin/configure.py
# Edit .env to set your admin email and optional site title.
sh bin/start.sh
sh bin/verify.sh
```

Open **http://localhost:8080** and **http://localhost:8080/wp-admin/**. Sign in as `siteadmin` using `WORDPRESS_ADMIN_PASSWORD` from your local `.env` file. Configuration generates random passwords, never prints them, and preserves an existing `.env`. Keep that file private; it is excluded from Git and the Docker build context. Editing the admin password or email in `.env` after installation does not change the existing WordPress account.

ACF is activated during setup. Use **ACF → Field Groups** to create your fields. This uses ACF's official upstream source, not the separate Secure Custom Fields fork. ACF Pro is not included and needs your own license and package.

The web port binds to loopback, and the database has no published port. To use a different port, change both `WORDPRESS_PORT` and `WORDPRESS_URL` in `.env` **before the first setup**. Later URL changes also require updating WordPress's `home` and `siteurl` options.

## Manage the installation

```sh
# WP-CLI commands
docker compose run --rm -T cli plugin list
docker compose run --rm cli user update siteadmin --prompt=user_pass

# Stop without deleting the database or uploaded files
docker compose down

# Restart an installed site
docker compose up -d --wait db wordpress
```

Named volumes retain the database and WordPress files, including uploads. `docker compose down --volumes` **deletes that data**. The setup command can be rerun against the same database; it preserves existing accounts and content and reinstalls the ACF version pinned in the image.

Add project PHP code under `wp-content/mu-plugins/`; it is mounted read-only into WordPress. Use ACF's JSON export or PHP export to keep field definitions in Git. Do not commit database exports, uploads containing private data, credentials, or licensed plugin packages.

## Versions and verification

The WordPress/PHP 8.3, WP-CLI, and MariaDB 11.4 images are pinned by digest in `Dockerfile` and `compose.yaml`. ACF 6.8.10 is pinned to upstream commit `fa4ceb7b2773ac520d9b3c36d7271853bccd7a8f`, and its archive checksum is verified during the build. The image includes WP-CLI and the ACF package, so setup does not need to download plugins at runtime.

`bin/verify.sh` checks the installed database, ACF activation, and a real ACF field write/read. It removes its temporary draft page afterward. GitHub Actions builds a fresh installation, checks HTTP, and repeats setup to check idempotence.

## Hosting and updates

This is configured for local use. To deploy publicly, select a Docker-capable server or adapt it to a WordPress host, set the real URL, configure HTTPS and a reverse proxy, change `WP_ENVIRONMENT_TYPE` to `production`, and arrange persistent storage and backups. Do not expose the initial WordPress installer on a public address. No hosting account, domain, or public deployment is configured by this repository.

Back up both named volumes before upgrades. Updating a base image alone does not replace WordPress core already stored in the volume; use WordPress's supported core update process and test the result. Update the ACF commit/checksum and rebuild before rerunning setup to change the managed ACF dependency. Check supported upstream versions and security updates regularly; digest pins do not update automatically.

Managed build environments using a private proxy CA can pass it as an optional BuildKit secret:

```sh
docker build --secret id=proxy_ca,src=/path/to/ca-bundle.pem -t wordpress-acf:local .
docker compose up -d --wait db wordpress
docker compose run --rm -T setup
```
