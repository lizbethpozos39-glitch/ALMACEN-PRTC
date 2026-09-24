@echo off
set FECHA=%date:~-4%-%date:~3,2%-%date:~0,2%
"C:\xampp\mysql\bin\mysqldump.exe" -u root inventario > "C:\xampp\respaldos\inventario_%FECHA%.sql"
