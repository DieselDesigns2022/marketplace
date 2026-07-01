-- Phase 8.5 correction: Personal is included/free; seller-enabled add-on licenses may be free or paid.
START TRANSACTION;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('personal','Personal','The Personal License is for personal use only. This license allows the buyer to use the design for themselves, gifts, personal projects, and non-profit use where no money is being made.

This license allows you to use the purchased file for personal projects only. You may create items for yourself or as gifts, but you may not sell, trade, donate for promotion, or profit from any item made with this file.

You may not share, upload, transfer, copy, resell, modify for resale, or distribute the digital file in any way. The file must remain private and under your control at all times.

This license does not allow commercial use, print-on-demand use, transfer sales, fabric production, wholesale production, VA access, or digital resale.',10,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('basic','Basic','The Basic License allows small business use for finished physical products only. This license is for buyers who personally make and sell finished physical products using their own equipment.

This license allows you to create and sell finished physical products using the purchased design.

Examples include shirts, tumblers, mugs, signs, decals applied to finished items, tote bags, ornaments, keychains, and similar completed products.

You must personally create the finished product yourself using equipment you personally own or directly operate.

This license does not allow you to sell production-ready items such as DTF transfers, sublimation transfers, screen prints, UV DTF decals, waterslides, vinyl transfers, sticker sheets, fabric, tattoos, or similar items.

You may not upload the file to any third-party printing service, print-on-demand platform, manufacturer, gang sheet website, shared drive, or outside production company.

You may not send the file to another person or business to produce items for you.

You may not sell, share, transfer, upload, or distribute the digital file.

This license does not allow POD use, wholesale production, extended production, fabric production, VA access, or digital resale.',20,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('commercial','Commercial','The Commercial License allows buyers to print and sell production-based physical products such as DTF transfers, stickers, sublimation transfers, screen prints, UV decals, and similar products — but only when they are produced using equipment the buyer personally owns.

This license allows you to create and sell physical production items using the purchased design, as long as you personally produce them yourself using equipment you own or directly operate.

Examples include DTF transfers, sublimation transfers, screen prints, stickers, waterslides, UV DTF decals, vinyl transfers, temporary tattoos, decals, and similar physical printed products.

You may not upload the file to any third-party printing service, gang sheet website, print shop, POD platform, manufacturer, shared production site, marketplace production service, or outside production company.

You may not send the file to another person or business to print, cut, press, manufacture, or produce items for you.

All production must be done by you, using your own equipment.

This license does not allow digital resale, file sharing, outsourcing, POD use, fabric production, VA access, reseller rights, or claiming the design as your own.

Files must remain private and under your control at all times.',30,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('pod','POD','The POD License allows the buyer to use the design with print-on-demand services such as Printify, Printful, Gelato, or similar production platforms.

This license allows you to upload the design to an approved print-on-demand platform for the purpose of selling finished physical products only.

You may sell POD products such as shirts, mugs, tumblers, bags, pillows, wall art, and similar finished physical items.

The file may only be uploaded to the POD platform needed to fulfill your own products. You are responsible for making sure the file stays private, protected, and unavailable to customers or other sellers.

You may not upload the file to public marketplaces as a digital download, editable file, template, clipart, PNG, SVG, sublimation file, transfer file, or any other downloadable product.

You may not share the file with another person, VA, designer, shop, team member, manufacturer, or business unless the correct additional license has been purchased.

This license does not allow transfer sales, fabric production, wholesale production, digital resale, or claiming ownership of the design.',40,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('wholesale','Wholesale','The Wholesale License is required when producing more than the standard allowed amount of finished physical products using a single design. This license is for larger production runs, boutique orders, bulk orders, and wholesale finished product sales.

This license allows you to produce and sell larger quantities of finished physical products using the purchased design.

This license is intended for larger production runs of finished physical products such as shirts, tumblers, mugs, bags, signs, decals, or similar completed items.

You may sell finished physical products retail or wholesale to boutiques, shops, events, or customers.

This license does not allow you to sell the digital file, transfer the file, share the file, upload the file for public access, or allow another business to use the file.

This license does not automatically allow POD, fabric production, transfer sales, VA access, digital resale, or reseller rights unless those license types are also purchased or specifically included.

If you plan to produce or sell 1,000 or more physical products of a single design, an Extended License is required.

All product images and mockups using the design must be visibly watermarked.',50,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('fabric-overseas','Fabric — Allows Overseas Printing','The Fabric License with Overseas Printing allows the buyer to use the design for fabric, textiles, and sewn-product production, including production through overseas fabric printers or manufacturers.

This license allows you to use the design for fabric or textile production.

This includes custom fabric, printed fabric, bows, blankets, clothing, handmade sewn goods, accessories, bags, baby items, and similar textile-based products.

You may send the file to an overseas fabric printer, textile printer, or manufacturer only for the purpose of producing your own licensed fabric or finished textile products.

The printer or manufacturer may not sell, reuse, share, distribute, display, or make the design available to any other customer or business.

You are responsible for making sure the production company protects the file and does not reuse or distribute it.

You may sell finished fabric-based products created with the licensed design.

You may not sell or distribute the digital file itself. You may not list the design as a downloadable seamless file, PNG, SVG, pattern file, or digital fabric design.

This license does not allow POD use, transfer sales, VA access, digital resale, reseller rights, or claiming ownership of the design unless those licenses are also purchased or clearly included.',60,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('fabric-no-overseas','Fabric — No Overseas Printing','The Fabric License without Overseas Printing allows the buyer to use the design for fabric, textiles, and sewn-product production, but the file may not be sent to overseas printers, manufacturers, or production companies.

This license allows you to use the design for fabric or textile production.

This includes custom fabric, printed fabric, bows, blankets, clothing, handmade sewn goods, accessories, bags, baby items, and similar textile-based products.

You may sell finished fabric-based products created with the licensed design.

You may only produce fabric yourself or through an approved domestic printer if the listing or designer allows it.

You may not send the file to overseas printers, overseas manufacturers, overseas fabric companies, overseas production partners, or any overseas third-party service.

You may not upload the file to any public fabric printing website, shared production website, marketplace, or platform where the file may be accessed by others.

You may not sell or distribute the digital file itself. You may not list the design as a downloadable seamless file, PNG, SVG, pattern file, or digital fabric design.

This license does not allow POD use, transfer sales, VA access, digital resale, reseller rights, or claiming ownership of the design unless those licenses are also purchased or clearly included.',70,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('va','VA','The VA License allows limited file access for a virtual assistant, employee, or approved helper. This license may be used by a business owner who needs help with their own files, or by a VA who purchases the file and license to use for their own clients.

This license allows one approved virtual assistant, employee, or helper to access the purchased file for business-related tasks.

Allowed tasks may include creating listings, mockups, product photos, marketing graphics, customer previews, product setup, or other approved design-related business tasks.

If the business owner purchases the file and VA License, the VA may only use the file for that business owner. The VA may not use the file for their own business, other clients, freebies, bundles, templates, products, or resale.

If the VA personally purchases the file and the correct VA License, the VA may use the file for their own clients.

However, the file must be protected at all times. Clients may only receive watermarked previews or finished flattened images when needed. Clients may not receive, download, access, or keep unwatermarked files.

The VA may not give clients access to the raw file, editable file, transparent PNG, SVG, layered file, Procreate file, stamp file, clipart file, seamless file, or any unwatermarked design file.

The VA may not upload the file to shared drives, team folders, Facebook groups, marketplace listings, public links, or any location where unauthorized people can access it.

The original purchaser remains fully responsible for the file and for anything done with it.

This license does not transfer ownership. It only gives limited usage access under the terms of the license.

If more than one VA, employee, helper, or client-facing assistant needs access, additional VA licensing may be required.',80,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('reseller-credit-required','Reseller — Credit Required','The Credit Required Reseller License allows the buyer to resell specific digital products through their own website or business, but they must clearly credit the original designer or business.

This license allows you to resell the licensed file through your own website or business when reseller rights are clearly included with the product purchased.

This may include clipart, lineart, digital files, Procreate stamps, templates, or similar digital products only when the product listing specifically allows reseller use.

Credit is required.

The product listing must clearly credit the original business or designer. Credit must be easy to see, easy to read, and clearly placed in the product listing.

Credit must include a hyperlink to the original designer’s Asset Moth store.

Example credit format: “Original design by [Designer/Business Name] on Asset Moth.”

The credit must be clickable and must link to the original designer’s Asset Moth shop.

This license does not give you ownership of the file.

Reseller rights do not give you the right to allow your own customers to resell, redistribute, share, give away, or claim ownership of the file.

You may not claim the original artwork as your own.

You may not trademark, copyright, register, or use the design as your logo or brand identity.

You may not include the file in design drives, memberships, mega bundles, freebie groups, shared folders, giveaways, or file-sharing communities unless the original listing clearly allows it.

This license only applies to the specific file or product purchased. It does not apply to the seller’s full shop, brand, future products, or other files.',90,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('reseller-no-credit-required','Reseller — No Credit Required','The No Credit Required Reseller License allows the buyer to resell specific digital products through their own website or business without giving visible credit to the original designer.

This license allows you to resell the licensed file through your own website or business when no-credit reseller rights are clearly included with the product purchased.

This may include clipart, lineart, digital files, Procreate stamps, templates, or similar digital products only when the product listing specifically allows reseller use.

Credit is not required with this license.

This license does not give you ownership of the file.

Reseller rights do not give you the right to allow your own customers to resell, redistribute, share, give away, or claim ownership of the file.

You may not claim the original artwork as your own.

You may not trademark, copyright, register, or use the design as your logo or brand identity.

You may not include the file in design drives, memberships, mega bundles, freebie groups, shared folders, giveaways, or file-sharing communities unless the original listing clearly allows it.

You may not sell the file in a way that suggests you created the original artwork unless the listing specifically grants that right.

This license only applies to the specific file or product purchased. It does not apply to the seller’s full shop, brand, future products, or other files.',100,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES ('extended-commercial','Extended Commercial','The Extended Commercial License allows the buyer to print and sell 1,000 or more physical products of a single design.

This license allows high-volume production and sale of physical products using the purchased design.

This license is required when producing or selling 1,000 or more physical products using one design.

Examples include large product launches, boutique collections, event inventory, retail collections, bulk business production, or high-volume handmade business use.

This license allows finished physical product sales only unless the listing clearly states otherwise.

You may not sell, share, transfer, upload, or distribute the digital file.

This license does not automatically allow print-on-demand use, fabric production, transfer sales, VA access, overseas production, third-party manufacturing, digital resale, or reseller rights unless those rights are clearly included in the product listing or purchased as separate licenses.

You may not claim the design as your own, trademark the design, copyright the design, or use it as a logo or main brand identity.

All mockups, product photos, customer previews, and promotional images must be visibly watermarked when the design is shown online.',110,1) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order), is_active=1;
UPDATE product_license_types plt
JOIN license_types old_lt ON old_lt.id = plt.license_type_id AND old_lt.license_key = 'fabric'
JOIN license_types new_lt ON new_lt.license_key = 'fabric-overseas'
SET plt.license_type_id = new_lt.id;
UPDATE license_types
SET name='Fabric (Legacy)', description='Legacy generic Fabric license replaced by Fabric — Allows Overseas Printing and Fabric — No Overseas Printing.', sort_order=999, is_active=0
WHERE license_key='fabric';
UPDATE product_license_types plt
JOIN license_types lt ON lt.id = plt.license_type_id
SET plt.sort_order = lt.sort_order;
COMMIT;
