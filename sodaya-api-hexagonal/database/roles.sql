-- Run once per server as a superuser:
--   psql -U postgres -v owner_password='...' -v app_password='...' -v maintenance_password='...' -f database/roles.sql

CREATE ROLE sodaya_owner LOGIN PASSWORD :'owner_password';
CREATE ROLE sodaya_app LOGIN PASSWORD :'app_password';
-- The only role that bypasses row-level security: batch tasks that go through every soda.
CREATE ROLE sodaya_maintenance LOGIN BYPASSRLS PASSWORD :'maintenance_password';

CREATE DATABASE sodaya_hexagonal OWNER sodaya_owner;
CREATE DATABASE sodaya_hexagonal_testing OWNER sodaya_owner;

REVOKE ALL ON DATABASE sodaya_hexagonal, sodaya_hexagonal_testing FROM PUBLIC;
GRANT CONNECT ON DATABASE sodaya_hexagonal, sodaya_hexagonal_testing TO sodaya_app, sodaya_maintenance;
