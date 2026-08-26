-- Backup de producto_variantes antes de eliminar el sistema de tallas S/M/L/XL
-- Generado: 2026-08-26 07:32:58
-- Motivo: colapso de precio por talla a un precio único por producto (ver CLAUDE.md)

DROP TABLE IF EXISTS producto_variantes;
CREATE TABLE `producto_variantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `talla` enum('S','M','L','XL') NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_producto_talla` (`producto_id`,`talla`),
  CONSTRAINT `producto_variantes_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (1, 1, 'S', '1895.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (2, 1, 'M', '2195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (3, 1, 'L', '2495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (5, 2, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (7, 3, 'S', '1895.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (8, 3, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (9, 3, 'L', '3195.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (10, 4, 'S', '1495.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (11, 4, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (12, 4, 'L', '2195.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (13, 4, 'XL', '2495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (14, 5, 'S', '1895.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (15, 5, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (16, 5, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (17, 6, 'S', '1695.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (18, 6, 'M', '1995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (19, 6, 'L', '2295.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (20, 7, 'M', '595.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (21, 8, 'M', '895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (22, 9, 'M', '1195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (23, 10, 'M', '1495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (25, 11, 'M', '1495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (31, 12, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (32, 13, 'XL', '11695.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (33, 14, 'M', '3495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (34, 15, 'M', '1795.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (35, 16, 'S', '695.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (36, 17, 'S', '895.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (37, 18, 'L', '3495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (38, 19, 'M', '1795.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (39, 20, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (40, 21, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (41, 22, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (42, 23, 'M', '1795.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (43, 24, 'M', '2695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (44, 25, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (45, 26, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (46, 27, 'L', '5895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (47, 28, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (56, 37, 'L', '4495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (57, 38, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (58, 39, 'M', '3195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (59, 40, 'L', '3595.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (61, 42, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (62, 43, 'M', '1995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (63, 44, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (64, 45, 'M', '2995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (65, 46, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (66, 47, 'XL', '3495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (67, 48, 'M', '2895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (68, 49, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (69, 50, 'L', '3695.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (70, 51, 'XL', '5995.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (71, 52, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (72, 53, 'L', '3995.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (73, 54, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (74, 55, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (75, 56, 'M', '2595.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (76, 57, 'M', '1995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (77, 57, 'L', '2495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (78, 57, 'XL', '2995.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (80, 58, 'L', '3895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (82, 59, 'L', '3495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (83, 60, 'L', '3495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (84, 60, 'XL', '4195.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (85, 61, 'XL', '3695.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (86, 62, 'M', '1295.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (87, 62, 'L', '1895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (88, 62, 'XL', '2295.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (89, 63, 'S', '395.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (90, 64, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (91, 65, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (92, 66, 'M', '995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (93, 67, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (94, 68, 'M', '2195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (95, 69, 'M', '2895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (96, 70, 'M', '2495.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (97, 71, 'L', '3295.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (98, 72, 'L', '2895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (99, 73, 'M', '3195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (100, 74, 'XL', '4495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (101, 75, 'M', '2395.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (102, 76, 'M', '3195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (103, 77, 'M', '2395.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (104, 78, 'XL', '4495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (105, 79, 'XL', '15495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (106, 80, 'L', '6895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (108, 81, 'S', '895.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (109, 82, 'S', '495.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (110, 83, 'S', '495.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (111, 84, 'S', '395.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (112, 85, 'M', '1995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (113, 86, 'S', '595.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (114, 87, 'M', '1295.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (115, 88, 'L', '4895.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (116, 89, 'M', '1095.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (117, 90, 'M', '1295.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (118, 91, 'M', '1895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (120, 93, 'M', '2095.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (121, 94, 'M', '2295.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (122, 95, 'S', '995.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (123, 96, 'M', '1195.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (124, 97, 'S', '395.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (125, 98, 'M', '995.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (126, 99, 'M', '895.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (127, 100, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (128, 101, 'M', '1595.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (129, 102, 'M', '695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (130, 103, 'L', '3095.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (131, 104, 'S', '1495.00', 1, 0);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (132, 104, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (133, 104, 'L', '2095.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (134, 104, 'XL', '2495.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (135, 105, 'M', '2095.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (136, 105, 'L', '2495.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (137, 105, 'XL', '2895.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (138, 106, 'L', '2195.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (139, 107, 'M', '1695.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (140, 108, 'M', '1795.00', 1, 1);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (144, 15, 'L', '2095.00', 1, 2);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (145, 15, 'XL', '2695.00', 1, 3);
INSERT INTO producto_variantes (id, producto_id, talla, precio, disponible, orden) VALUES (161, 109, 'M', '1495.00', 1, 1);
