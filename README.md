# Simple forum

## Phpunit Live reload

```bash
chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c 'docker exec chat-app phpunit -c specs/specs.xml --testsuite=api --color=always'

chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c 'docker exec chat-app phpunit phpunit --bootstrap specs/autoload.php specs/thread/ThreadTest.php --color=always'
 
``` 
### Fix System limit for number of file watchers reached
```bash
echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p
```

## Fix copy line eclipse shortcut
```bash
gsettings set org.gnome.desktop.wm.keybindings switch-to-workspace-down "['']"
gsettings set org.gnome.desktop.wm.keybindings switch-to-workspace-up "['']"

```