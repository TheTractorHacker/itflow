<div id="top"></div>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Stargazers][stars-shield]][stars-url]
[![Commits][commit-shield]][commit-url]
[![GPL License][license-shield]][license-url]

<div align="center">

  <h3 align="center">ITFlow — MSP Edition</h3>

  <p align="center">
    A fork of <a href="https://github.com/itflow-org/itflow">ITFlow</a> with extended MSP workflow features built by <a href="https://foleyit.com">Foley IT</a>.
    <br />
    <br />
    <a href="https://github.com/itflow-org/itflow">Upstream Project</a>
    ·
    <a href="https://docs.itflow.org">Docs</a>
    ·
    <a href="https://github.com/TheTractorHacker/itflow/releases">Releases</a>
    ·
    <a href="https://github.com/TheTractorHacker/itflow/issues">Report Bug</a>
  </p>
</div>

---

> **This is a fork.** It tracks the upstream [itflow-org/itflow](https://github.com/itflow-org/itflow) and merges updates regularly. All original credit goes to the ITFlow contributors. MSP-specific additions are maintained here by Foley IT.

---

<!-- ABOUT -->
## About

**ITFlow MSP Edition** is a hardened, feature-extended build of ITFlow — the free and open-source IT documentation, ticketing, and accounting platform for managed service providers.

This fork adds real-world MSP dispatch and scheduling workflows that go beyond the upstream project, while staying in sync with upstream security patches and improvements.

<!-- MSP ADDITIONS -->
## What's Added in This Fork

### Ticketing
- **Ticket categories** with parent/group hierarchy and collapsible grouped list view
- **Inline pill-style dropdowns** — change Category, Assigned Tech, Priority, and Status directly from the ticket list without opening the ticket
- **Syncro-style appointments** — end time, duration picker (30 min – 8 hr), Remote/Onsite toggle, appointment notes, and live preview
- **Ticket reply draft autosave** — localStorage autosave with Restore/Discard banner so replies survive accidental navigation

### Calendar & Scheduling
- **Outlook Calendar push sync** — each technician connects their Microsoft account once; scheduled tickets automatically create, update, and cancel events in their personal Outlook calendar via Microsoft Graph API
- **iCal subscription feed** — per-user webcal:// URL for subscribing scheduled tickets into Outlook Classic, Apple Calendar, or Google Calendar
- **Per-tech calendar colors** — each technician picks a color shown on the ITFlow dispatch calendar

### Contracts & SLA
- **SLA tracking on contracts** — define response/resolution hours per priority tier
- **Contract billing frequency** — Monthly, Quarterly, Annual, or Other
- **Live SLA hint on ticket add** — shows expected response/resolution time when a contract is selected

### Worksheets
- **Worksheet template drag-and-drop field reordering**
- **Worksheet percent counter** — accurately counts all field types

### Security Fixes (beyond upstream)
- Fixed authorization bypass on ticket charge handlers (client access not enforced)
- Fixed XSS in outtake/worksheet signature storage
- Fixed SQL injection in contract name logAction call

<!-- SYNCING WITH UPSTREAM -->
## Keeping Up With Upstream

This fork merges upstream changes periodically:

```bash
git fetch origin        # origin = itflow-org/itflow
git merge origin/master
git push fork master    # fork = TheTractorHacker/itflow
```

<!-- GETTING STARTED -->
## Getting Started

Installation is the same as upstream ITFlow. See the [official docs](https://docs.itflow.org/installation).

```bash
wget -O itflow_install.sh https://github.com/itflow-org/itflow-install-script/raw/main/itflow_install.sh
bash itflow_install.sh
```

After installing, replace the files with this fork's content or clone this repo directly into your web root.

<!-- RELEASES -->
## Releases

Fork releases follow the format `vX.Y.Z-msp`. See [Releases](https://github.com/TheTractorHacker/itflow/releases) for changelogs.

| Release | Notes |
|---------|-------|
| v1.2.5-msp | Outlook push sync, per-tech calendar colors, modal bug fixes |
| v1.2.4-msp | Upstream sync, security fixes |
| v1.2.3-msp | Categories, SLA, contracts, worksheets, inline ticket actions |

## License

ITFlow is distributed under the GPL License. This fork inherits the same license. See [`LICENSE`](https://github.com/itflow-org/itflow/blob/master/LICENSE) for details.

## Security

If you find a security issue in the upstream project, report it [here](https://github.com/itflow-org/itflow/security/policy).
For issues specific to this fork, open an [issue](https://github.com/TheTractorHacker/itflow/issues).

<!-- MARKDOWN LINKS & IMAGES -->
[contributors-shield]: https://img.shields.io/github/contributors/TheTractorHacker/itflow.svg?style=for-the-badge
[contributors-url]: https://github.com/TheTractorHacker/itflow/graphs/contributors
[stars-shield]: https://img.shields.io/github/stars/TheTractorHacker/itflow.svg?style=for-the-badge
[stars-url]: https://github.com/TheTractorHacker/itflow/stargazers
[license-shield]: https://img.shields.io/github/license/TheTractorHacker/itflow.svg?style=for-the-badge
[license-url]: https://github.com/itflow-org/itflow/blob/master/LICENSE
[commit-shield]: https://img.shields.io/github/last-commit/TheTractorHacker/itflow?style=for-the-badge
[commit-url]: https://github.com/TheTractorHacker/itflow/commits/master
