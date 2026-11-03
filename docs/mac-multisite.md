# Mac multisite testing

This setup creates a main WordPress site plus two test sites, with ACF available on all three. It uses subdirectories on localhost: no DNS or hosts-file changes are needed.

## 1. Install the tools

Install [Docker Desktop for Mac](https://docs.docker.com/desktop/setup/install/mac-install/). Select Apple silicon for an M-series Mac or Intel for an Intel Mac; check Apple menu > About This Mac if unsure. Open Docker from Applications and wait for its engine to run. Docker requires a supported macOS release and at least 4 GB of Mac RAM.

Open Terminal and check:

~~~sh
docker version
docker compose version
git --version
python3 --version
~~~

The Docker version output must include a running Server. If Git is missing, run `xcode-select --install` and finish Apple's installer. If Python 3 is missing, install it from [python.org](https://www.python.org/downloads/macos/) and reopen Terminal. PHP, WP-CLI, Apache, and MariaDB run inside Docker; you do not need to install them on your Mac.

The pinned images offer linux/amd64 and linux/arm64 variants. Docker chooses the architecture, so leave platform selection at its default.

## 2. Clone and configure

In Terminal, change to your usual projects folder, then run:

~~~sh
git clone https://github.com/TerminalDogma/Wordpress-MCP.git
cd Wordpress-MCP
python3 bin/configure.py
open -e .env
~~~

In TextEdit, set `WORDPRESS_ADMIN_EMAIL` and optionally `WORDPRESS_TITLE`, then save. Keep `WORDPRESS_URL=http://localhost:8080` and `WORDPRESS_PORT=8080` for the URLs below. Your username is `siteadmin`; find its generated password in `WORDPRESS_ADMIN_PASSWORD`.

The private `.env` file is ignored by Git. Keep it with your installation. Editing the account settings in that file after installation does not change the existing WordPress account.

Already cloned the earlier version? Run `git pull --ff-only` inside that checkout instead. Commit or stash local code changes before pulling, and preserve your `.env` and Docker volumes.

## 3. Create and verify the network

~~~sh
sh bin/start.sh multisite
sh bin/verify.sh
python3 bin/verify-http.py multisite
~~~

Allow several minutes for the first download and build. Setup creates the subdirectory network, adds Site A and Site B, network-activates ACF, and writes Apache's multisite routing rules. Verification checks ACF writes and reads on every site, static file routing, and login to all dashboards.

If the original single-site installation already exists, the command converts it using WP-CLI, keeping its content and administrator account. Back up meaningful existing data before converting. Subsequent setup runs preserve the network and avoid duplicate sites. Running single-site setup later does not convert a network back.

## 4. Open and log in

| Purpose | URL |
| --- | --- |
| Main site | http://localhost:8080/ |
| Network Admin | http://localhost:8080/wp-admin/network/ |
| Site A | http://localhost:8080/site-a/ |
| Site A dashboard | http://localhost:8080/site-a/wp-admin/ |
| Site B | http://localhost:8080/site-b/ |
| Site B dashboard | http://localhost:8080/site-b/wp-admin/ |

Use `siteadmin` and the password in `.env`. That account is the network Super Admin. If you converted an existing installation, keep using its existing administrator credentials. **My Sites > Network Admin > Sites** lets you switch sites and add more.

Use `localhost` consistently; switching to `127.0.0.1` can interfere with stored URLs and cookies.

## 5. Test ACF and code

In Site A's dashboard, open **ACF > Field Groups**, create a text field assigned to Pages, and save a value on a new page. Repeat on Site B to test another configuration. Network activation makes ACF available everywhere; field groups, field values, and content remain separate for each site. It does not synchronize them automatically.

Install plugins through **Network Admin > Plugins > Add New**. Network-activate plugins that should run everywhere. To test one on a single site, leave it inactive at network level, then activate it in that site's Plugins screen as Super Admin.

Install and network-enable themes through Network Admin. Each site can select its own theme under **Appearance > Themes**.

For PHP experiments, put a `.php` file directly in your checkout's `wp-content/mu-plugins/` folder. It loads automatically on every site, and Mac edits are available on the next request. Regular plugins and themes installed through the dashboard live in the Docker volume; export or bind-mount their code separately when you want to edit and version them on your Mac.

~~~sh
# List the main site plus both test sites
docker compose run --rm -T cli site list --fields=blog_id,url

# Target Site A
docker compose run --rm -T cli --url=http://localhost:8080/site-a/ plugin list

# Add another test site
docker compose run --rm -T cli site create --slug=site-c --title="Test Site C" --private
~~~

## 6. Stop and resume

~~~sh
# Stop; retain the database, settings, and uploads
docker compose down

# Resume the installed network (open Docker Desktop first)
docker compose up -d --wait db wordpress
~~~

`docker compose down --volumes` deletes your local WordPress data. Use that option only when intentionally discarding the network. The repository and `.env` alone are not a backup of your site content.

## Back up existing data

With the installation running:

~~~sh
mkdir -p backups
docker compose exec -T db sh -c 'exec mariadb-dump --single-transaction -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > backups/database.sql
docker compose cp wordpress:/var/www/html backups/wordpress-files
~~~

Keep both the SQL dump and WordPress files private; the backups folder is ignored by Git.

## Troubleshooting

- **Cannot connect to Docker:** open Docker Desktop and wait for the engine, then rerun `docker version`.
- **Port 8080 is occupied:** before the first install, set both `WORDPRESS_PORT=8081` and `WORDPRESS_URL=http://localhost:8081` in `.env`. Use 8081 in the browser. Keep the URL stable once the network is installed.
- **Subsite dashboard or CSS returns 404:** rerun `sh bin/start.sh multisite` to reapply the multisite rules.
- **Network Admin is missing:** ensure you ran setup with `multisite` and signed in as the Super Admin.
- **Login verification fails after conversion:** the account retains its existing password. To intentionally reset it, run `docker compose run --rm cli user update siteadmin --prompt=user_pass` interactively.
- **Inspect a failed start:** run `docker compose ps` and `docker compose logs --tail=80 wordpress db`. Keep your `.env` and full container configuration private when sharing diagnostics.

## Official references

- [Docker Desktop for Mac](https://docs.docker.com/desktop/setup/install/mac-install/)
- [Creating a WordPress network](https://developer.wordpress.org/advanced-administration/multisite/create-network/)
- [WP-CLI multisite installation](https://developer.wordpress.org/cli/commands/core/multisite-install/)
- [WP-CLI conversion](https://developer.wordpress.org/cli/commands/core/multisite-convert/)
- [WP-CLI site creation](https://developer.wordpress.org/cli/commands/site/create/)
