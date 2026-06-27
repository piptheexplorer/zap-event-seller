Event Ticket Seller
===================

A WordPress ticket selling plugin using ACF Pro blocks, Stripe Checkout, ticket orders, customer ticket lookup, and FPDF ticket downloads.

Shortcodes
----------
[ets_thank_you]
[ets_my_tickets]

Required
--------
- ACF Pro for the ticket block fields.
- FPDF at: lib/fpdf/fpdf.php
- Stripe publishable and secret keys in Ticket Seller settings.

Notes
-----
This cleaned build removes development-only files such as node_modules, .DS_Store and __MACOSX.
PDF downloads are handled before theme output via template_redirect.
