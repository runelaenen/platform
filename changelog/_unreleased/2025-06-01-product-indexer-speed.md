---
title: Improved performance of product indexer
author: Rune Laenen
author_email: rune@laenen.me
author_github: runelaenen
---
# Core
* Changed the `ProductCategoryDenormalizer::fetchMapping` query to use `product_category` relation table directly without needing to join it with `product` table.
