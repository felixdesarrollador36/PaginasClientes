UPDATE ml_heroes SET image_url = CONCAT('assets/heroes_img/', 
  CASE 
    WHEN role LIKE 'Tirador%' THEN 'Tirador'
    WHEN role LIKE 'Mago%' THEN 'MAGO'
    WHEN role LIKE 'Tanque%' THEN 'Tanque'
    WHEN role LIKE 'Asesino%' THEN 'Asesino'
    WHEN role LIKE 'Combatiente%' THEN 'Combatiente'
    WHEN role LIKE 'Apoyo%' THEN 'APOYO'
    ELSE 'MAGO'
  END,
  '/', LOWER(name), '.png') 
WHERE image_url IS NOT NULL AND image_url != '';

SELECT id, name, role, image_url FROM ml_heroes LIMIT 10;
