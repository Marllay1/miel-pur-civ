-- Rôle d'équipe unique : les gestionnaires catalogue et commandes deviennent administrateurs,
-- puis les deux rôles restreints sont supprimés. Sans effet sur une base neuve.

UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin')
WHERE role_id IN (SELECT id FROM roles WHERE name IN ('catalog_manager', 'order_manager'));

DELETE FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE name IN ('catalog_manager', 'order_manager'));

DELETE FROM roles WHERE name IN ('catalog_manager', 'order_manager');
