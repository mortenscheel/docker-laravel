# Docker Laravel
A CLI tool to initialize and manage a Docker environment for a Laravel project.

## Installation
```shell
composer global require mortenscheel/docker-laravel
```
Or download `builds/docker-laravel` and place in PATH.

## Usage
I recommend creating an alias to make everyday use simpler.
```shell
alias d="~/.composer/vendor/bin/docker-laravel"
```
### Initialize project
```shell
d init
```
This will copy the `/docker` folder and `/docker-compose.yml` to your project
It will also update your `.env` (after you've approved the changes)

### Proxied commands
When you run the command inside an initialized Laravel project, docker-laravel will automatically proxy the commands.
**Examples**
```shell
d up -d # runs docker compose up -d. All compose commands are supported
d composer require foo/bar # Commands starting with composer are sent to composer in the app container
d artisan route:list # Sent to artisan in the app container
d a route:list # a is aliased to artisan
d route:list # if the first param includes a colon, it's interpreted as an artisan command
d debug some:command # Runs some:command in Artisan with xdebug enabled
d xdebug on/off/status # Controls xdebug in the app container
d shell/zsh/bash # Enter the app container as the non-root (laravel) user
d root-shell # Enter app as root
```
Note: this documentation is a work in progress. To see all the available subcommands and features, take a look at `DefaultCommand`
