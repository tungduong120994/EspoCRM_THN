# UAT deployment — 2026-09-11

## Update — 2026-09-16

DNS now resolves `crm-uat.thnglobal.vn` to `103.90.226.80`. Public HTTPS is
verified with a Let's Encrypt certificate, valid through 2026-12-15. Adding an
explicit UAT TLS domain and recreating only the UAT app triggered issuance;
production containers and the shared Traefik container were not restarted.

The logistics release from commit `705e5a42` is installed, with the follow-up
export ACL compatibility fix in `Services/Logistics/Access.php`. UAT schema/cache
rebuild completed. Inventory/finance navigation and the delivery PDF template are
updated on UAT only. Backups are in `/home/thnglobal/crm-uat/backups/20260916`.

Admin and three dedicated test users are now active: `uat-admin`, `uat-sales`,
`uat-warehouse`, `uat-management`. Copied production users remain inactive.
Admin access remains in `UAT-ACCESS.txt`; generated test-user credentials are in
`UAT-TEST-USERS.json` (mode 600), both only on the server outside the webroot.

See [verification results](VERIFIED-20260916.md). The dated sections below describe
the original deployment; their pending-DNS and one-active-user statements have
been superseded by this update.

## Actual deployment

- Host: `103.90.226.80`; SSH user: `thnglobal`.
- Directory: `/home/thnglobal/crm-uat` (mode 700).
- Compose: `compose.snapshot.yaml`, project `crm-uat`.
- Containers: `crm-uat-app-1`, `crm-uat-db-1`.
- DB: `crm_uat`, account `crm_uat`, volume `crm-uat_db-data`.
- App files/uploads: independent `./app` copy, not a production bind mount.
- Fresh transactional production dump taken on 2026-09-11, about 267 MB.
  All production tables were InnoDB; no production triggers were present.
  Files were copied separately while production remained online; this is not
  an atomic database-and-upload snapshot. Check attachments used in UAT tests.
- DB/application images match production: MariaDB 11.3, EspoCRM 9.2.5.
- Each UAT container is capped at 0.5 CPU / 512 MB RAM. Host kernel does not
  support Docker swap limits. This deployment shares physical host resources.

Both UAT networks are internal. Only Traefik and the UAT app share
`crm-uat_edge`; the DB joins only `crm-uat_database`. UAT has no published
ports or outbound default gateway. Traefik was connected to the UAT edge
network without restarting production containers. No production data was
written or production DB credentials reused by UAT.

System SMTP, copied mail accounts, webhooks, integrations and scheduled jobs
are disabled. Copied login sessions are deleted; copied users are inactive,
with passwords and API keys cleared. Business record ownership is retained.
Only `uat-admin` is active. Runtime uses Apache directly, without installer or
cron startup. Business settings and custom entities come from production.

## Verification completed

- PHP config lint, Compose validation, cache clear and rebuild passed.
- API login as `uat-admin` passed.
- Created, updated and read `UAT isolation verification 20260911` via Account API.
- Read-only production SQL found zero matching test records and zero `uat-admin` users.
- UAT connection attempts to BOTH production DB container IPs, public DB port,
  and external HTTPS were blocked, including after attaching Traefik.
- UAT database grants are scoped to `crm_uat` only.
- Exactly one active UAT user; zero active webhooks/integrations.
- UAT proxy returned HTTP 200 using a forced hostname resolution.
  TLS verification was disabled for that routing-only check: a valid public
  certificate has NOT been verified because DNS is still missing.
- Production HTTPS returned HTTP 200 after deployment.

## Remaining DNS/TLS work

Public nameservers: `ns1.vietnix.net`, `ns2.vietnix.net`, `nsbak.vietnix.net`.
No access to the Vietnix DNS account has been provided. Create only this record:

| Type | Name | Value |
| --- | --- | --- |
| A | crm-uat | 103.90.226.80 |

After propagation, verify the Traefik `le` certificate is issued, then test the
public URL with normal certificate verification and browser login. If ACME does
not retry, recreate only the UAT app to refresh its router configuration.

## Access and operations

New admin credentials and an SSH tunnel command are stored only on the server:
`/home/thnglobal/crm-uat/UAT-ACCESS.txt` (mode 600). No passwords are in this repo.
The tunnel permits local testing before DNS is available; obtain its current
target from the access file (container IP may change after recreation).

From `/home/thnglobal/crm-uat`:

```sh
docker compose -p crm-uat --env-file .env -f compose.snapshot.yaml ps
docker compose -p crm-uat --env-file .env -f compose.snapshot.yaml stop
# Resume UAT only:
docker compose -p crm-uat --env-file .env -f compose.snapshot.yaml up -d
```

Traefik's edge-network attachment persists across restart but not container
recreation. If Traefik is recreated, reconnect it with
`docker network connect crm-uat_edge traefik`, or include that external network
in its deployment configuration during planned proxy maintenance.
Use `stop` rather than `down`: the proxy attachment keeps the edge network in use.
Do not rerun snapshot import/sanitization or use `down -v` on an in-use UAT.

`verify-snapshot.py` creates a retained test record; it is a deployment check,
not a recurring health probe. The original snapshot contains production data
and remains in the protected server directory, outside the app webroot.
