# Senoamadi-financial-services
#1. Project Overview
```text
Senoamadi Financial Services (SFS) began as a financial services website providing financial and business-oriented functionality.

The current repository is implemented primarily in PHP and contains an existing application foundation including authentication, user functionality, administration, money tracking, loans, investments, proposals, transactions and a relational database. The repository also contains a V2.0.0 development directory containing the existing web application and database structure.

SFS V2.0.0 expands that foundation into a broader concept:

A people-centered capital circulation network.

The emphasis is no longer only on individual financial accounts or isolated financial services.

The emphasis becomes:

How does capital move through the network?
```
#Senoamadi Financial Services — V2.0.0
```text
SENOAMADI-FINANCIAL-SERVICES
│
├── README.md
│   └── Main repository documentation
│
├── LICENSE
│   └── MIT License
│
├── SENOAMADI_FINANCIAL_V2.0.0
│   │
│   ├── README.md
│   │   └── V2.0.0 architecture / setup / project direction
│   │
│   ├── LICENSE
│   │   └── MIT License for V2.0.0
│   │
│   ├── SOURCE CODE
│   │   │
│   │   └── htdocs
│   │       │
│   │       ├── index.php
│   │       │   └── Main SFS entry point
│   │       │
│   │       ├── config.php
│   │       │   └── Database / application configuration
│   │       │
│   │       ├── login.php
│   │       │   └── Authentication entry point
│   │       │
│   │       │
│   │       ├── landing-page
│   │       │   │
│   │       │   ├── about.html
│   │       │   │   └── About SFS
│   │       │   │
│   │       │   ├── purpose.html
│   │       │   │   └── SFS purpose / philosophy
│   │       │   │
│   │       │   ├── solution.html
│   │       │   │   └── Problem / solution explanation
│   │       │   │
│   │       │   ├── services.html
│   │       │   │   └── SFS services
│   │       │   │
│   │       │   ├── contact.html
│   │       │   │   └── Contact information
│   │       │   │
│   │       │   ├── privacy-policy.html
│   │       │   │   └── Privacy policy
│   │       │   │
│   │       │   └── terms-and-conditions.html
│   │       │       └── Terms and conditions
│   │       │
│   │       │
│   │       ├── files
│   │       │   │
│   │       │   ├── SFS-LOGO.png
│   │       │   ├── SFS.png
│   │       │   ├── SENOAMADI_BANK.png
│   │       │   ├── Senoamadi-financials.jpeg
│   │       │   ├── business.avif
│   │       │   ├── invest2.avif
│   │       │   ├── money.jpg
│   │       │   └── judge.jpg
│   │       │       └── Existing SFS visual assets
│   │       │
│   │       │
│   │       ├── main
│   │       │   │
│   │       │   ├── user_page.php
│   │       │   │   └── Member / user environment
│   │       │   │
│   │       │   ├── personal.php
│   │       │   │   └── Personal financial environment
│   │       │   │
│   │       │   ├── profile.php
│   │       │   │   └── User profile
│   │       │   │
│   │       │   ├── track_money.php
│   │       │   │   └── Money / capital tracking
│   │       │   │
│   │       │   ├── suggestion.php
│   │       │   │   └── User suggestions / feedback
│   │       │   │
│   │       │   ├── logout.php
│   │       │   │   └── Session termination
│   │       │   │
│   │       │   └── admin_page.php
│   │       │       └── Existing administration environment
│   │       │
│   │       │
│   │       ├── member
│   │       │   │
│   │       │   ├── dashboard.php
│   │       │   │   └── SFS member overview
│   │       │   │
│   │       │   ├── capital.php
│   │       │   │   └── Member capital position
│   │       │   │
│   │       │   ├── activity.php
│   │       │   │   └── Capital activity / history
│   │       │   │
│   │       │   ├── opportunities.php
│   │       │   │   └── Available opportunities
│   │       │   │
│   │       │   ├── funding.php
│   │       │   │   └── Funding participation
│   │       │   │
│   │       │   ├── returns.php
│   │       │   │   └── Returns / distributions
│   │       │   │
│   │       │   └── documents.php
│   │       │       └── Member documents
│   │       │
│   │       │
│   │       ├── business
│   │       │   │
│   │       │   ├── dashboard.php
│   │       │   │   └── Business overview
│   │       │   │
│   │       │   ├── profile.php
│   │       │   │   └── Business profile
│   │       │   │
│   │       │   ├── funding-request.php
│   │       │   │   └── Business funding request
│   │       │   │
│   │       │   ├── opportunities.php
│   │       │   │   └── Business opportunities
│   │       │   │
│   │       │   ├── performance.php
│   │       │   │   └── Business performance
│   │       │   │
│   │       │   └── documents.php
│   │       │       └── Business documentation
│   │       │
│   │       │
│   │       ├── opportunities
│   │       │   │
│   │       │   ├── index.php
│   │       │   │   └── Opportunity registry
│   │       │   │
│   │       │   ├── view.php
│   │       │   │   └── Opportunity details
│   │       │   │
│   │       │   ├── create.php
│   │       │   │   └── Create opportunity
│   │       │   │
│   │       │   ├── assessment.php
│   │       │   │   └── Opportunity assessment
│   │       │   │
│   │       │   └── verification.php
│   │       │       └── Opportunity verification
│   │       │
│   │       │
│   │       ├── capital
│   │       │   │
│   │       │   ├── accounts.php
│   │       │   │   └── Capital accounts
│   │       │   │
│   │       │   ├── pools.php
│   │       │   │   └── Capital pools
│   │       │   │
│   │       │   ├── allocations.php
│   │       │   │   └── Capital allocation
│   │       │   │
│   │       │   ├── flow.php
│   │       │   │   └── Capital movement
│   │       │   │
│   │       │   └── matching.php
│   │       │       └── Capital-to-opportunity matching
│   │       │
│   │       │
│   │       ├── finance
│   │       │   │
│   │       │   ├── ledger.php
│   │       │   │   └── Financial ledger
│   │       │   │
│   │       │   ├── transactions.php
│   │       │   │   └── Transaction records
│   │       │   │
│   │       │   ├── repayments.php
│   │       │   │   └── Repayment processing
│   │       │   │
│   │       │   ├── returns.php
│   │       │   │   └── Return calculations
│   │       │   │
│   │       │   └── reconciliation.php
│   │       │       └── Financial reconciliation
│   │       │
│   │       │
│   │       ├── intelligence
│   │       │   │
│   │       │   ├── risk.php
│   │       │   │   └── Risk assessment
│   │       │   │
│   │       │   ├── scoring.php
│   │       │   │   └── Financial / opportunity scoring
│   │       │   │
│   │       │   ├── matching.php
│   │       │   │   └── Matching engine
│   │       │   │
│   │       │   └── analytics.php
│   │       │       └── SFS analytics
│   │       │
│   │       │
│   │       ├── admin
│   │       │   │
│   │       │   ├── dashboard.php
│   │       │   │   └── SFS Control Centre
│   │       │   │
│   │       │   ├── users.php
│   │       │   │   └── User administration
│   │       │   │
│   │       │   ├── businesses.php
│   │       │   │   └── Business administration
│   │       │   │
│   │       │   ├── opportunities.php
│   │       │   │   └── Opportunity administration
│   │       │   │
│   │       │   ├── capital.php
│   │       │   │   └── Capital monitoring
│   │       │   │
│   │       │   ├── funding.php
│   │       │   │   └── Funding review
│   │       │   │
│   │       │   ├── risk.php
│   │       │   │   └── Risk review
│   │       │   │
│   │       │   ├── approvals.php
│   │       │   │   └── Approval workflow
│   │       │   │
│   │       │   ├── transactions.php
│   │       │   │   └── Transaction management
│   │       │   │
│   │       │   ├── reports.php
│   │       │   │   └── System reports
│   │       │   │
│   │       │   ├── audit.php
│   │       │   │   └── Audit logs
│   │       │   │
│   │       │   ├── permissions.php
│   │       │   │   └── Role / permission control
│   │       │   │
│   │       │   └── settings.php
│   │       │       └── System configuration
│   │       │
│   │       │
│   │       ├── api
│   │       │   │
│   │       │   ├── auth.php
│   │       │   ├── users.php
│   │       │   ├── businesses.php
│   │       │   ├── capital.php
│   │       │   ├── opportunities.php
│   │       │   ├── funding.php
│   │       │   ├── transactions.php
│   │       │   ├── risk.php
│   │       │   └── matching.php
│   │       │       └── Internal / future external API endpoints
│   │       │
│   │       │
│   │       ├── core
│   │       │   │
│   │       │   ├── database.php
│   │       │   │   └── PDO database layer
│   │       │   │
│   │       │   ├── auth.php
│   │       │   │   └── Authentication functions
│   │       │   │
│   │       │   ├── permissions.php
│   │       │   │   └── RBAC
│   │       │   │
│   │       │   ├── validation.php
│   │       │   │   └── Input validation
│   │       │   │
│   │       │   ├── security.php
│   │       │   │   └── Security utilities
│   │       │   │
│   │       │   ├── ledger.php
│   │       │   │   └── Ledger functions
│   │       │   │
│   │       │   └── audit.php
│   │       │       └── Audit logging
│   │       │
│   │       │
│   │       ├── assets
│   │       │   │
│   │       │   ├── css
│   │       │   │   ├── sfs.css
│   │       │   │   ├── member.css
│   │       │   │   ├── business.css
│   │       │   │   └── admin.css
│   │       │   │
│   │       │   ├── js
│   │       │   │   ├── sfs.js
│   │       │   │   ├── network.js
│   │       │   │   ├── charts.js
│   │       │   │   └── admin.js
│   │       │   │
│   │       │   └── images
│   │       │
│   │       └── storage
│   │           ├── documents
│   │           ├── reports
│   │           └── logs
│   │
│   │
│   ├── DATABASE
│   │   │
│   │   ├── database.sql
│   │   │   └── Existing database foundation
│   │   │
│   │   ├── schema
│   │   │   ├── users.sql
│   │   │   ├── businesses.sql
│   │   │   ├── capital.sql
│   │   │   ├── opportunities.sql
│   │   │   ├── funding.sql
│   │   │   ├── transactions.sql
│   │   │   ├── ledger.sql
│   │   │   ├── risk.sql
│   │   │   ├── returns.sql
│   │   │   ├── approvals.sql
│   │   │   └── audit.sql
│   │   │
│   │   └── migrations
│   │       └── Incremental V2 database changes
│   │
│   │
│   └── DOCUMENTATION
│       │
│       ├── ARCHITECTURE.md
│       │   └── Technical architecture
│       │
│       ├── CAPITAL-NETWORK.md
│       │   └── SFS capital circulation model
│       │
│       ├── FINANCIAL-ENGINE.md
│       │   └── Ledger / transaction design
│       │
│       ├── INTELLIGENCE.md
│       │   └── Risk / matching / analytics
│       │
│       ├── ADMIN-CONTROL.md
│       │   └── Admin architecture
│       │
│       ├── DATABASE.md
│       │   └── Database documentation
│       │
│       ├── SECURITY.md
│       │   └── Security architecture
│       │
│       └── API.md
│           └── API documentation
│
│
└── LEGACY
    │
    └── Senoamadi-financial V 1.6.3
        └── Previous implementation retained for reference
```
