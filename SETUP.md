# FKR Reykjavík — Developer Setup

## Requirements

- [DDEV](https://ddev.com/get-started/) installed
- [Docker](https://www.docker.com/products/docker-desktop/) running
- [Composer](https://getcomposer.org/) installed

---

## Setup steps

### 1. Clone the repo

```bash
git clone https://github.com/bjarnib23/fkr-reykjavik.git
cd fkr-reykjavik
```

### 2. Install PHP dependencies

```bash
cd drupal
composer install
```

### 3. Configure the config sync directory

Open `drupal/web/sites/default/settings.php` and add this line (around line 262):

```php
$settings['config_sync_directory'] = '../config/sync';
```

### 4. Start DDEV

```bash
ddev start
```

### 5. Install Drupal with existing config

```bash
ddev drush site:install --existing-config -y
```

This sets up the database and imports all content types automatically.

### 6. Get a login link

```bash
ddev drush uli
```

Open the URL in your browser — you're logged in as admin.

### 7. Open the site

```bash
ddev launch
```

---

## Importing a database dump (optional)

If you have a database dump from a teammate (e.g. with price list data already added):

```bash
ddev import-db --file=dump.sql.gz
```

---

## Useful commands

| Command | What it does |
|---|---|
| `ddev start` | Start the local environment |
| `ddev stop` | Stop the local environment |
| `ddev launch` | Open the site in browser |
| `ddev drush uli` | Get an admin login link |
| `ddev drush config:export` | Export config changes to git |
| `ddev drush config:import` | Import config from git |
| `ddev drush cr` | Clear cache |

---

## JSON API endpoints

React fetches data from these URLs (replace domain with `https://fkr-reykjavik.ddev.site` locally):

| Data | Endpoint |
|---|---|
| Price list | `/jsonapi/node/fkr_price_item?sort=field_weight` |
| FAQ | `/jsonapi/node/fkr_faq?sort=field_faq_weight` |
| Bookings (admin only) | `/jsonapi/node/fkr_booking` |
| Gift cards (admin only) | `/jsonapi/node/fkr_gift_card` |

---

## After making config changes in the Drupal UI

Always export and commit your config changes:

```bash
ddev drush config:export
git add drupal/config/sync
git commit -m "Description of what you changed"
git push
```
