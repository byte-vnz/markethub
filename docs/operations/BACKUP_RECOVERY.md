# Backup and recovery
Use the installed MySQL client with an interactive password prompt or protected option file. Never put passwords on the command line or in Git.

Before release or migration: take an InnoDB consistent mysqldump --single-transaction backup of markethub_lgu, copy private storage separately, and securely escrow APP_KEY and environment configuration. Encrypt backups, restrict access and define retention with the LGU.

Restore into a NEW isolated database first. Verify table counts, foreign keys, bill/payment/reversal totals and sample tenant statements before switching application configuration. Keep the original database and backup until restore acceptance. No restore was executed during development. Establish and rehearse RPO/RTO with operations; a successful backup command alone is not a recovery test.
