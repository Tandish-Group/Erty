# Open-source release checklist

This repository's history contains data that must never be published: an old `.env`, database dumps with real user data, large logs and an uploads archive. **Do not push this repository, or any clone of it, to a public remote.** Publish a fresh repository built from the final working tree instead.

## 1. Prepare the tree

- [ ] `git status` is clean, and every change you want to publish is committed.
- [ ] Nothing sensitive is in the tree. Each of these should print nothing, or only `.env.example`:
  - `git ls-files | grep -Ei '\.env|\.sql|\.dump|\.zip|\.log|backup'`
  - `grep -rIl --exclude-dir=vendor -E 'password\s*=\s*["'"'"'][^"'"'"']+' app config database routes`
- [ ] `.env.example` holds placeholders only: no keys, passwords or real email addresses.
- [ ] `php artisan test` passes.
- [ ] A fresh install works: `php artisan erty:install` on an empty Postgres database, then sign in.
- [ ] The demo works: `php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder` (local).
- [ ] `docs/INSTALL.md` matches what the install actually does.

## 2. Publish from a fresh history

```bash
# Export the tracked files at the commit you want to release; history is left behind.
git archive --format=tar --prefix=erty/ HEAD | tar -x -C /tmp
cd /tmp/erty
git init -b main
git add .
git commit -m "Erty: initial open-source release"
git remote add origin git@github.com:<org>/erty.git
git push -u origin main
```

- [ ] In the new repository, check again that `git log --stat` contains a single commit and no `.env`, dump or log files.
- [ ] Add the licence file chosen for the release (`LICENSE`), and update `README.md` with it.
- [ ] Rotate every credential that was ever in the old `.env` or the old dumps: mail, Paystack, M-Pesa, Youverify, the exchange-rate API key, the database, `APP_KEY`.

## 3. Keep the private repository private

The original repository keeps its history for internal use. Leave it private, and keep `docs/internal/` (ignored) out of the public tree.
