monitor
=======

Welcome to the open-source repository for FacuArmo's servers' monitoring and self-restart application.

Feel free to look around the code, make improvements and provide suggestions and feedback.

## Introduction

This is a rather simple tool part of the "administrative tools" category allowing server owners to easily run a looping script that'll keep track of down servers and restart them straight through the shell.

## Features

- Self-restart on timeout
- Supports  **Half-Life 1** and all of its mods, including (but not limited to) **Counter-Strike 1.6**
- Supports **Minecraft** servers using version auto-detection
- Supports both Windows and Linux! (*although self-restart will work just on Linux*)
- Supports custom probing intervals and timeouts

## Screenshots
![Success status on probe](https://i.ibb.co/5WLMHzR/unknown.png)

## Dependencies

- A Linux-based OS (or Windows, disabling the self-restart feature)
- PHP 7.2 (or greater)
- Composer

## Installation

- Clone the repository wherever you want to
- Change to that directory and, then, install the required Composer libraries by running `composer install`
- On startup (on root's cron, or whatever script that spawns right next to your dedicated server) run a screen session, something like this: `screen -d -m sudo php /home/server/monitor/run.php --game=minecraft --server=127.0.0.1 --jarfile=paper-`
- You'll now see the application logging data onto its screen session

## Usage

Run the application using the following command:

    php .\run.php --game=hl1|minecraft --server=127.0.0.1 [--server=192.168.0.25:27021] [--jarfile=paper-1.18.1.jar] [--timeout=30] [--interval=30]

Example:

    php .\run.php --game=hl1 --server=127.0.0.1

## Notes

- Omitting the port number will result in probing the default port for the selected game
- The "jarfile" argument is only required if probing Minecraft servers
- "jarfile" is being interpreted as a wildcard parameter, if you plan on moving Minecraft server version frequently, feel free to type just a keyword that's specific to your server, i.e.: `paper-`
- In order to prevent crashes and failed self-restarts, you **must** run the script as `root`

## Development

On its current stage, the application is completely stable and usable. Although further testing might be necessary in order to meet the standards of some production environments.

## Contributions

If you liked this tool or you feel like there's anything to improve on or optimize, feel free to provide your suggestions or, better yet, **submit a pull request to the repo!**

## Credits

- To [@Rixafy](https://github.com/Rixafy) for providing [such an amazing PHP query library for Minecraft servers](https://github.com/PHP-Minecraft/MinecraftQuery).
- To [@xPaw](https://github.com/xPaw) for providing a [stable and completely trustworthy Source + GoldSource PHP query library](https://github.com/xPaw/PHP-Source-Query).

## License

This project is licensed under the [GNU Affero General Public License v3.0](LICENSE).
