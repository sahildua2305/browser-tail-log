# browser-tail-log : realtime log stream in browser

browser-tail-log is php application for serving tail -F output to the browser.

The best thing about this application is that it never reads the log file entirely. It just seeks the required number of lines or the newly added lines from the log file. Hence it's useful with log files which can't be opened in memory.

## Features
 - memory efficient (at max 4 MB per client)
 - time efficient
 - auto-scrolling
 - multiple clients supported
 - realtime synchronization

## Usage

Take clone of the repo in your localhost and start the socket server by running `start_server.sh` - 
    
    git clone https://github.com/sahildua2305/browser-tail-log.git
    cd browser-tail-log
    sudo bash start_server.sh

Web interface runs on **http://localhost/browser-tail-log**

## Screenshot
![Screenshot](screenshots/screenshot1.png?raw=true "Screenshot")
