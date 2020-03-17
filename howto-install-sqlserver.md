How to install SQL Server
=========================

1. Download & Install SQL server with "basic" option: https://www.microsoft.com/sql-server/sql-server-2019
2. After installation, open console using `sqlcmd -s localhost\SQLEXPRESS -E`
3. Use this commands to enable `sa` account:
   ```
   USE [master]
   GO
   ALTER LOGIN [sa] WITH PASSWORD = 'your-password', CHECK_POLICY=OFF
   GO
   ALTER LOGIN [sa] ENABLE
   GO
   CREATE DATABSE nextras_dbal_test
   GO
   ```
4. Enable username login in registry: in `HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL12.SQLEXPRESS\MSSQLServer` set `LoginMode` to `2`, where `MSSQL12.SQLEXPRESS` is a name of your db instance. 
5. Restart server.     
