@echo off
TITLE Archive
cd /d %~dp0

"bin/php/php" archive.php
pause