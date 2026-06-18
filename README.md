# Vapor for Nextcloud 33

- [English](README.md)
- [简体中文](README.zh-CN.md)

An easy-to-use web interface for Aria2 and yt-dlp (youtube-dl).

- Search for torrents within the app from multiple BT sites
- Control Aria2 and manage download tasks from the web
- Harnessing the power of yt-dlp to download videos and audio from 700+ sites (YouTube, Youku, Dailymotion, Twitter, Facebook and many more)

<img width="800" alt="nc2" src="https://user-images.githubusercontent.com/3911975/132008308-dec2a7ba-4387-441e-9ded-538d61fbccf0.png">
<img width="800" alt="nc4" src="https://user-images.githubusercontent.com/3911975/142444998-54dd54a6-0c8e-4d49-8188-270964a99c50.png">
<img width="800" alt="nc5" src="https://user-images.githubusercontent.com/3911975/142445020-27ec389a-5437-4d28-acc0-5e757fd6897d.png">

---

## About This Fork

This fork fixes compatibility issues with **Nextcloud 33** that caused the original app to break. The following deprecated internal Nextcloud APIs were replaced with current supported equivalents:

- Replaced deprecated `OC\Files\Filesystem::getLocalFile()` with `OCP\Files\IRootFolder`
- Replaced deprecated `QueryBuilder::execute()` with `executeQuery()` / `executeStatement()`
- Replaced deprecated `OC\AllConfig` with `OCP\IConfig`
- Replaced deprecated `OC\Core\Command\Base` with Symfony `Command`
- Replaced deprecated `OC\Files\Utils\Scanner` with `OCP\Files\IRootFolder`
- Built and included compiled JS/CSS assets

**Tested on:**
- Nextcloud 33
- Ubuntu 22.04
- PHP 8.3

Original app by [shiningw](https://github.com/shiningw/vapor).
Forked from [darkpisto](https://github.com/darkpisto/vapor).
NC33 compatibility fixes by [wolfravenous](https://github.com/wolfravenous/vapor).

---

## Requirements

- Nextcloud 33
- Ubuntu 22.04
- PHP 8.3
- Command line access with sudo
- aria2 installed on the server

---

## Installation

**1. Install aria2 if not already installed:**
```bash
sudo apt install aria2
```

**2. Navigate to your Nextcloud apps directory and clone the repo:**
```bash
cd /var/www/nextcloud/apps
sudo git clone https://github.com/wolfravenous/vapor.git
```

**3. Set correct ownership:**
```bash
sudo chown -R www-data:www-data /var/www/nextcloud/apps/vapor
```

**4. Create the bin directory and download yt-dlp:**
```bash
sudo mkdir -p /var/www/nextcloud/apps/vapor/bin
sudo wget -O /var/www/nextcloud/apps/vapor/bin/yt-dlp https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp
sudo chmod 755 /var/www/nextcloud/apps/vapor/bin/yt-dlp
sudo chown www-data:www-data /var/www/nextcloud/apps/vapor/bin/yt-dlp
```

**5. Install Node.js 20.x if not already installed:**
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

**6. Build the frontend assets:**
```bash
cd /var/www/nextcloud/apps/vapor
sudo npm install
sudo npm run build
```

**7. Enable the app:**
```bash
sudo -u www-data php8.3 /var/www/nextcloud/occ app:enable vapor
```

Then navigate to the Vapor app in your Nextcloud instance and configure your settings.

---

## Scanning Downloaded Files

After downloading files, run this command to make them visible in Nextcloud files. Replace `yourusername` and the path with your own:
```bash
sudo -u www-data php8.3 /var/www/nextcloud/occ files:scan yourusername --path="/yourusername/files/Music"
```

---

## Updating yt-dlp Manually

If the in-app update button doesn't work, update yt-dlp manually:
```bash
sudo wget -O /var/www/nextcloud/apps/vapor/bin/yt-dlp https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp
sudo chmod 755 /var/www/nextcloud/apps/vapor/bin/yt-dlp
sudo chown www-data:www-data /var/www/nextcloud/apps/vapor/bin/yt-dlp
```

---

## Building Front-End Code

If you need to rebuild the frontend assets, NPM 7.0+ and Node 14.0+ are required:
```bash
npm install
npm run build
```

---

## License

AGPL - See [LICENSE](LICENSE) for details.
