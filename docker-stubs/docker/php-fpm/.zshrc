#!/usr/bin/env bash
alias pa="php artisan"
alias tinker="php artisan tinker"
alias cr="composer require"
alias ci="composer install"
alias cda="composer dump-autoload"

export LANG='en_US.UTF-8'
export LANGUAGE='en_US:en'
export TERM=xterm

###############
# Completions #
###############

# Completions
autoload -U compinit
compinit -C

# Arrow key menu for completions
zstyle ':completion:*' menu select

# Case-insensitive (all),partial-word and then substring completion
zstyle ':completion:*' matcher-list 'm:{a-zA-Z}={A-Za-z}' 'r:|[._-]=* r:|=*' 'l:|=* r:|=*'

# Autocomplete command line switches for aliases
setopt completealiases

###########
# History #
###########

# number of lines kept in history
export HISTSIZE=1000
# number of lines saved in the history after logout
export SAVEHIST=1000
# location of history
export HISTFILE=~/.zsh_history
# append command to history file once executed
setopt inc_append_history
# only show past commands that include the current input
bindkey "^[[A" history-beginning-search-backward
bindkey "^[[B" history-beginning-search-forward


# Automatically use cd when paths are entered without cd
setopt autocd

# Use emacs keybinds, since they're modeless and closer to bash defaults
bindkey -e
export EDITOR=nano

autoload -U promptinit; promptinit
prompt adam1
