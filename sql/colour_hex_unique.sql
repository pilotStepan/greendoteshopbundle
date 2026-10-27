-- Colour parameters store the hex in `parameter`.`data` and the colour name is resolved by joining on hex,
-- so two colours with the same hex make every such variant show both names (invoices, filters, Heureka).
-- The index is case-insensitive under utf8mb4_unicode_ci, so '#55ad8d' and '#55AD8D' collide too.

-- Run first; the index creation fails while this returns rows. Fix duplicates by hand
-- (give one colour its own hex, or delete it and repoint affected `parameter`.`data`).
SELECT `hex`, GROUP_CONCAT(CONCAT(`id`, ':', `name`) SEPARATOR ' | ') AS `colours`, COUNT(*) AS `n`
FROM `colour`
GROUP BY `hex`
HAVING `n` > 1;

CREATE UNIQUE INDEX `UNIQ_colour_hex` ON `colour` (`hex`);
