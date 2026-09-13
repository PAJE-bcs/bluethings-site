# bluethings-site
Statische website voor Blue Things (bluethings.be) van Blue Cat Services BV.

- `site/` — de publieke bestanden die naar Kinamo worden gedeployed (index.html, admin.html, legal.html).
- `.github/workflows/deploy.yml` — zet `site/` bij elke push naar `main` via FTPS op Kinamo.

Secrets (Settings → Secrets and variables → Actions): FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR.
