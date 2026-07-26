# DRMS Deployment and Annual Cost Plan

**Prepared:** 2026-07-26
**Status:** Planning only; no infrastructure has been provisioned
**Scope:** A zero-cost, scheduled client-demo environment and a separate budgeted pilot/production environment

## 1. Governing Boundaries

This plan does not authorize a public production deployment.

- `CONSTRAINTS.md` leaves production hosting, domain, infrastructure sizing, email, queues, backups, and credentials undecided and requires separate approval before public deployment.
- `PLAN.md` schedules production HTTPS, queues, the scheduler, logging, backup/restore, and deployment guidance for Phase 11.
- `TASKS.md` records Phase 11 security and operational readiness and Phase 12 UAT as pending.

The demo track below must therefore use demonstration data only. The production track may begin only after its approval gates are satisfied.

## 2. Recommendation

Use two deliberately separate environments:

1. **Client demonstration:** Run DRMS on the existing development computer and expose it only during scheduled demonstrations through a free Cloudflare Quick Tunnel.
2. **Approved pilot/production:** Use one prepaid, self-managed Linux VPS with 4 GB RAM, PHP 8.4, MySQL 8.4, Nginx, a queue worker, and the Laravel scheduler. The cost-efficient candidate is Hostinger KVM 1, procured on a 12-month billing cycle. If procurement permits a 24-month commitment, its currently advertised promotion has a lower annualized cost.

Do not copy the demonstration database, credentials, application key, receiving-box tokens, or printed QR labels into production.

## 3. Track A — Free Scheduled Client Demo

### 3.1 Architecture

| Component | Demo choice | Incremental cost |
|---|---|---:|
| Application host | Existing development computer | $0 |
| Web application | Existing Laravel 12 / PHP 8.4 installation | $0 |
| Database | Existing local MySQL 8.4 database with demo records only | $0 |
| Public connection | Cloudflare Quick Tunnel with a random `trycloudflare.com` URL | $0 |
| TLS | Included by the tunnel | $0 |
| Domain | Temporary tunnel URL; no purchased domain | $0 |
| Email | Laravel `log` mailer; no real messages | $0 |
| Demo backups | Local disposable demo snapshot | $0 |
| **Annual incremental total** | Scheduled demonstrations only | **$0** |

Cloudflare states that Quick Tunnels are free, produce a random public URL, have no uptime SLA, and are intended only for testing and development. They are therefore appropriate for scheduled demonstrations but not for an always-available pilot or production service.

Source: [Cloudflare Quick Tunnels](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/)

### 3.2 Demo Operating Procedure

#### One-time preparation

1. Create a separate untracked demo environment configuration.
2. Use a separate demo database containing fabricated organizations, users, records, names, and contact details.
3. Set production-like safety flags even though the environment is temporary:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `MAIL_MAILER=log`
4. Create strong, temporary Level 1 and Level 2 demonstration passwords.
5. Prepare a repeatable demo database reset procedure.
6. Install and verify `cloudflared`.
7. Confirm the firewall does not expose MySQL or the local Laravel port directly to the internet.

#### Before each demonstration

1. Pull only the approved demonstration code revision.
2. Reset the demo database to its known fabricated baseline.
3. Run the applicable automated tests, formatter check, dependency audit, and frontend production build.
4. Start Laravel on a loopback address, not on all network interfaces.
5. Start the database-backed queue worker if the demonstrated phase requires it.
6. Start a Cloudflare Quick Tunnel to the local Laravel port.
7. Set the application URL to the newly generated HTTPS tunnel address and clear/rebuild cached configuration.
8. Open the application through the public tunnel and perform a smoke test:
   - Login and logout
   - Level 1 unit isolation
   - Level 2 administration
   - Document registration and routing
   - Receiving-box inventory
   - QR scan from a mobile device
9. Generate the demonstration receiving-box QR label through the active tunnel URL.

The application currently generates a receiving-box QR code from the active route URL. Because a Quick Tunnel URL changes between sessions, previously generated demo QR labels will not be reusable. Generate or display a new QR label during each demo session.

#### During the demonstration

1. Share the URL only with scheduled participants.
2. Keep the demonstration operator present.
3. Use only fabricated records and identities.
4. Do not enable real email delivery.
5. Do not represent this environment as a production system or an official government service.

#### After the demonstration

1. Stop the tunnel immediately.
2. Stop the Laravel process and demo queue worker.
3. Invalidate or replace temporary demo passwords.
4. Reset or delete client-entered demo data.
5. Review application logs for errors without retaining participant personal data.

### 3.3 Demo Acceptance Criteria

- No real, confidential, or production data is present.
- The public URL is available only during the scheduled demonstration.
- Debug mode is disabled.
- MySQL is not reachable from the public internet.
- Role and organizational-unit isolation smoke checks pass.
- A mobile device can scan the QR generated for the current tunnel session.
- The tunnel and temporary credentials are disabled after the session.

### 3.4 Optional Always-Available Free Demo

If clients must access the demo without an operator, an Oracle Cloud Always Free Ampere VM is a possible fallback. Oracle currently documents up to 2 OCPUs and 12 GB RAM for eligible Always Free tenancies. However, capacity can be unavailable and idle instances may be reclaimed. This option remains suitable only for a disposable proof of concept.

Source: [Oracle Cloud Always Free resources](https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm)

## 4. Track B — Approved and Budgeted Pilot/Production

### 4.1 Approval Gates

Do not procure or deploy the production environment until all of the following are recorded:

1. Phase 11 security and operational-readiness gate passes.
2. Backup and restore is rehearsed outside production.
3. Phase 12 UAT is signed off with no unresolved critical or high-severity defects.
4. The user separately approves public production infrastructure.
5. The responsible organization approves:
   - Hosting provider and data-center region
   - Data-processing terms
   - Official domain or subdomain
   - Named technical and administrative owners
   - Annual budget and renewal method
   - Backup retention and incident contacts

### 4.2 Recommended Efficient Architecture

Use one VPS for the initial low-volume pilot:

- Hostinger KVM 1 or an equivalent VPS
- 1 vCPU
- 4 GB RAM
- 50 GB NVMe storage
- Ubuntu 24.04 LTS
- Nginx
- PHP 8.4 FPM and required Laravel extensions
- MySQL 8.4 LTS bound to localhost/private access only
- Composer
- Node.js 24 used for controlled frontend builds
- Supervisor managing the Laravel database queue worker
- One cron entry running `php artisan schedule:run` every minute
- Let's Encrypt TLS
- Host firewall and provider firewall
- Weekly provider image backup
- Nightly encrypted MySQL and configuration backup to independent object storage

Hostinger currently advertises KVM 1 with 4 GB RAM, 50 GB NVMe storage, and free weekly backups. KVM VPS billing supports 1-, 12-, and 24-month periods, and longer periods usually have a lower average monthly price.

Sources:

- [Hostinger VPS pricing](https://www.hostinger.com/vps-hosting)
- [Hostinger VPS billing periods](https://www.hostinger.com/support/1583589-how-to-pay-for-hostinger-services-in-advance/)
- [Hostinger VPS backups](https://support.hostinger.com/en/articles/1583232-how-to-back-up-or-restore-a-vps)

Choose Singapore if it is offered in the checkout for the selected VPS; otherwise choose the nearest approved Asian region, normally Malaysia. Data-center availability must be confirmed at purchase because provider capacity and location lists can change.

### 4.3 Domain Plan

Preferred production address:

- `records.<agency-controlled-domain>`
- `drms.<agency-controlled-domain>`
- An approved subdomain beneath the responsible office's existing `.gov.ph` or `deped.gov.ph` domain

An agency-controlled subdomain is preferred because it preserves institutional trust and normally has no additional registrar expense for this application.

If an official domain is not yet available, do not make an unofficial commercial domain appear to be the final government service. A temporary commercial domain may be used only when clearly labeled as a pilot and approved by the organization.

Use DNSSEC where supported. Let's Encrypt provides free TLS certificates.

Sources:

- [DICT `.gov.ph` registration requirements](https://cms-cdn.e.gov.ph/DICT/pdf/DICT-Citizens-Charter-2025-1st-Edition.pdf)
- [Let's Encrypt](https://letsencrypt.org/)
- [Cloudflare Registrar at-cost registration and renewal](https://developers.cloudflare.com/registrar/)

### 4.4 Production Provisioning Sequence

#### Procurement

1. Capture a dated checkout quotation for 12 months.
2. Record both the introductory charge and the non-promotional renewal rate.
3. Prefer annual prepayment over month-to-month billing.
4. Use an organization-controlled account, payment method, recovery email, and MFA.
5. Record the renewal owner and renewal date.
6. Do not commit for more than one year unless the responsible organization accepts the provider and the early-termination risk. A 24-month promotion may be used only with explicit procurement approval.

#### Server baseline

1. Create an organization-owned, non-root administrator account.
2. Require SSH keys and disable password-based SSH after recovery access is verified.
3. Disable direct remote root login.
4. Permit only required inbound traffic, normally ports 80 and 443; restrict SSH by source where practical.
5. Keep MySQL bound to localhost or a private interface.
6. Enable automatic security updates and log rotation.
7. Configure system time synchronization and the approved application timezone.

#### Application deployment

1. Deploy an approved Git revision rather than copying a working directory.
2. Install locked Composer and npm dependencies.
3. Build frontend assets.
4. Create a new production application key and production-only credentials.
5. Configure at minimum:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - Final HTTPS `APP_URL`
   - MySQL production credentials
   - Database sessions, cache, and queues
   - Approved log and mail configuration
6. Run migrations with the production flag after taking a pre-deployment backup.
7. Cache Laravel configuration, routes, events, and views where supported by the final application.
8. Configure Supervisor for the queue worker.
9. Configure the Laravel scheduler.
10. Restart queue workers after every deployment.
11. Run the approved production smoke tests.

Laravel recommends a process monitor such as Supervisor for persistent queue workers and one scheduler cron entry that invokes `schedule:run` every minute.

Sources:

- [Laravel queues and Supervisor](https://laravel.com/docs/12.x/queues)
- [Laravel task scheduling](https://laravel.com/docs/12.x/scheduling)

#### Production data initialization

1. Start from clean MySQL migrations and approved production seed data.
2. Do not import demo users or demo records.
3. Create named production administrators through the approved secure process.
4. Create actual organizational units only after data ownership is confirmed.
5. Generate receiving-box tokens on the final production domain.
6. Print permanent QR labels only after final DNS and HTTPS are verified.

### 4.5 Backup and Recovery

Use two independent backup layers:

1. **Provider layer:** Included weekly VPS backup for whole-server recovery.
2. **Application layer:** Nightly encrypted, transaction-consistent MySQL dump and required configuration backup uploaded to an object-storage account owned by the organization.

Initial retention target:

- 14 daily database backups
- 8 weekly backups
- 12 monthly backups
- Pre-deployment backup before every migration

Cloudflare R2 currently includes 10 GB-month of Standard storage each month at no charge. Storage beyond the free allowance is listed at $0.015 per GB-month, with free internet egress. R2 is suitable as the independent object-storage layer only after credentials, encryption, retention, and restore procedures are approved.

Source: [Cloudflare R2 pricing](https://developers.cloudflare.com/r2/pricing/)

Required controls:

- Encrypt backups before upload.
- Keep object-storage credentials outside the application repository.
- Prevent the application database user from deleting backups.
- Alert when a nightly backup fails.
- Perform and record a restore rehearsal before pilot release and at least quarterly during the pilot.
- A successful upload is not sufficient evidence; a restored database must boot and pass defined integrity checks.

### 4.6 Monitoring and Operations

Monitor at least:

- HTTPS availability and certificate renewal
- CPU, memory, and disk usage
- MySQL availability and disk growth
- Failed Laravel queue jobs
- Scheduler execution
- Application error rate
- Backup completion and restore-test status
- OS and dependency security updates

Define named contacts for deployment, security incidents, database recovery, domain/DNS, and client support before release.

## 5. Annual Cost Analysis

### 5.1 Pricing Assumptions

- Currency: US dollars
- Pricing snapshot: 2026-07-26
- Taxes, foreign-exchange charges, procurement fees, and administrator labor are excluded.
- Promotional prices can change at any time. The provider checkout quotation is authoritative.
- The budget uses published renewal pricing where possible so the system remains affordable after the introductory period.

### 5.2 Recommended VPS Costs

Hostinger currently advertises KVM 1 at $6.49/month for a 24-month promotional term and $11.99/month on the published two-year renewal term.

| Item | Calculation | Cost |
|---|---:|---:|
| Promotional 24-month payment | $6.49 × 24 | $155.76 upfront |
| Promotional annualized equivalent | $155.76 ÷ 2 | $77.88/year |
| Published renewal payment | $11.99 × 24 | $287.76 per renewal term |
| Renewal annualized equivalent | $287.76 ÷ 2 | $143.88/year |
| Two-year promotional saving versus published renewal equivalent | $287.76 − $155.76 | $132.00 |
| Percentage difference | $132.00 ÷ $287.76 | 45.9% |

The exact 12-month checkout price is not publicly fixed in the cited page. For yearly procurement:

- Obtain a dated 12-month quotation.
- Approve only if it does not exceed the conservative infrastructure ceiling of **$143.88 per year before tax**.
- Record the renewal price separately.
- Do not describe a temporary promotion as the permanent annual cost.

### 5.3 Lean Annual Production Budget

| Cost area | Year-one efficient target | Conservative renewal budget | Notes |
|---|---:|---:|---|
| VPS | $77.88 annualized under current 24-month promotion | $143.88/year | Use 12-month billing if required by procurement; exact checkout quote required |
| Official agency subdomain | $0 | $0 | Assumes an existing agency-controlled domain |
| TLS | $0 | $0 | Let's Encrypt |
| Provider weekly backup | $0 | $0 | Included in the cited VPS offer |
| Independent object backup, first 10 GB | $0 | $0 | Within current R2 monthly free allowance |
| DNS | $0 | $0 | Existing agency DNS or approved free DNS service |
| Transactional email | $0 initially | Not yet decided | Phase 8 must approve agency SMTP, a provider, or a feature flag |
| **Infrastructure subtotal** | **$77.88/year equivalent** | **$143.88/year** | Excludes tax, exchange rate, and labor |

Add a 15% procurement contingency for taxes, exchange-rate movement, and small storage overages:

- Promotional annualized planning amount: approximately **$90/year**
- Conservative renewal planning amount: approximately **$166/year**
- Recommended approval ceiling: **$200/year**, excluding paid email service and administrator labor

If an official agency subdomain is unavailable, reserve an additional **$20/year planning allowance** for a temporary non-premium commercial domain. The actual registration and renewal quote must be obtained before purchase.

### 5.4 Alternatives

| Option | Verified infrastructure cost | Annual cost observation | Decision |
|---|---:|---|---|
| Existing computer + Quick Tunnel | $0 | $0, but scheduled and operator-dependent | Recommended for client demos only |
| Oracle Always Free | $0 when eligible | Capacity and idle-reclamation risk | Optional disposable demo fallback |
| Hostinger KVM 1 | $6.49/month current 24-month promotion; $11.99/month published renewal | $77.88 promotional annual equivalent; $143.88 renewal annual equivalent | Recommended cost-efficient pilot candidate |
| DigitalOcean 2 GB Droplet | $12/month | $144/year, plus $28.80/year for weekly backups at 20% | Stronger billing transparency but less RAM at a higher backed-up cost |
| Laravel Cloud smallest always-on app compute | $5/month for the cited small-compute example | At least $60/year for application compute, plus MySQL, storage, backups, and any plan charge | Easiest managed option, but obtain a calculator quote before approval |

DigitalOcean bills monthly rather than providing an annual prepayment discount. Its current 2 GB Droplet is $12/month, and weekly backups are 20% of Droplet cost, producing a base annual infrastructure figure of $172.80 before domain and offsite application backups.

Sources:

- [DigitalOcean Droplet pricing and backup percentage](https://www.digitalocean.com/pricing/droplets)
- [Laravel Cloud pricing](https://laravel.com/cloud/docs/pricing)

## 6. Cost-Control Rules

1. Use the free scheduled demo until the product and client interest justify recurring cost.
2. Do not keep duplicate staging and production VPS instances running during the initial pilot.
3. Buy a 12-month term after approval; avoid month-to-month billing.
4. Consider the 24-month promotion only after the provider, region, and operational process have passed review.
5. Budget from renewal price, not introductory price.
6. Use an existing official subdomain when available.
7. Keep MySQL, database queues, sessions, and cache on the same initial VPS to avoid premature managed-service costs.
8. Use included weekly server backups plus low-cost independent application backups.
9. Do not purchase Redis, a managed database, load balancing, autoscaling, or separate worker servers until measured usage demonstrates a need.
10. Review CPU, memory, disk, queue latency, and database size after 30, 60, and 90 pilot days before resizing.

## 7. Promotion and Rollback Checklist

### Before production launch

- Phase 11 and Phase 12 gates approved
- Provider and annual budget approved
- Official domain active
- HTTPS verified
- Production secrets generated
- Demo data absent
- MySQL 8.4 clean migration and seed verified
- Full automated suite and frontend build pass
- Queue worker and scheduler verified
- Backup upload and restore rehearsal pass
- Mobile QR workflow succeeds on the final domain
- Permanent QR labels generated only from the final domain
- Monitoring and incident contacts active

### Deployment rollback

1. Stop new deployments and place the application in controlled maintenance mode if required.
2. Preserve logs and the failed release for diagnosis.
3. Restore the previous approved code release.
4. Roll back only migrations proven safe and reversible.
5. Restore the pre-deployment database backup when data compatibility requires it and the authorized owner approves.
6. Restart PHP and queue workers.
7. Run the defined smoke and authorization checks.
8. Document the incident, impact, evidence, and recovery result.

## 8. Final Procurement Recommendation

For the first approved low-volume pilot, request an annual operating authorization of **up to $200 per year**, excluding staff labor and any later paid email plan.

At procurement:

1. Prefer a 12-month Hostinger KVM 1 term if the checkout price is at or below the $143.88 pre-tax server ceiling.
2. If a 24-month commitment is allowed and the current $6.49/month promotion remains available, the upfront server payment would be $155.76 and the annualized server cost would be $77.88.
3. Confirm the nearest approved Asian data center before payment.
4. Record the renewal price and renewal owner.
5. Re-evaluate the provider and actual resource usage before renewal.
