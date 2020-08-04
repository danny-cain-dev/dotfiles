#!/bin/bash

if [ -f ~/.bashrc -a -z "$BASHRC_DONE" ]
then
    source ~/.bashrc
fi
eval $(/home/danny/.linuxbrew/bin/brew shellenv)
