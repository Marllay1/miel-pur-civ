-- Photographies : remplace les illustrations SVG du catalogue initial par de vraies photos
-- (photos publiées par l'entreprise sur Facebook, ou photos d'illustration libres de droits, voir app/config/photos.php).
-- Sans effet sur une base neuve (le catalogue initial utilise déjà les photos) ni sur les images ajoutées en administration.

UPDATE product_images SET path = '/assets/img/photos/fb-bouteille-fond-jaune.webp', alt = 'Bouteille de Miel Pur de Côte d''Ivoire tenue en main sur fond jaune' WHERE path = '/assets/img/products/jar-amber.svg';

INSERT INTO product_images (product_id, path, alt, sort_order, is_demo, created_at)
SELECT product_id, '/assets/img/photos/fb-bouteilles-trio.webp', 'Trois bouteilles de Miel Pur de Côte d''Ivoire, étiquettes avant et arrière', 1, 0, created_at
FROM product_images WHERE path = '/assets/img/photos/fb-bouteille-fond-jaune.webp';

INSERT INTO product_images (product_id, path, alt, sort_order, is_demo, created_at)
SELECT product_id, '/assets/img/photos/fb-bouteilles-duo.webp', 'Deux bouteilles de miel ambré Miel Pur de Côte d''Ivoire', 2, 0, created_at
FROM product_images WHERE path = '/assets/img/photos/fb-bouteille-fond-jaune.webp';

UPDATE product_images SET path = '/assets/img/photos/wm-miel-cremeux.webp', alt = 'Photo d''illustration : pot de miel crémeux à côté d''un pot de miel liquide' WHERE path = '/assets/img/products/jar-cream.svg';

UPDATE product_images SET path = '/assets/img/photos/wm-filet-miel.webp', alt = 'Photo d''illustration : filet de miel qui s''écoule' WHERE path = '/assets/img/products/bucket.svg';

UPDATE product_images SET path = '/assets/img/photos/wm-pains-cire.webp', alt = 'Photo d''illustration : deux pains de cire d''abeille' WHERE path = '/assets/img/products/wax.svg';

UPDATE product_images SET path = '/assets/img/photos/fb-stand-coffrets.webp', alt = 'Stand de vente : bouteilles de miel alignées et paniers cadeaux emballés' WHERE path = '/assets/img/products/box.svg';
