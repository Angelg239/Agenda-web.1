USE agenda;

ALTER TABLE eventos
  ADD COLUMN categoria_id INT NULL AFTER categoria;

UPDATE categorias
   SET nombre = CASE id
     WHEN 1 THEN 'trabajo'
     WHEN 2 THEN 'personal'
     WHEN 3 THEN 'estudio'
     WHEN 4 THEN 'deporte'
   END
 WHERE id BETWEEN 1 AND 4;

INSERT INTO categorias (id, nombre)
VALUES (5, 'ocio')
ON DUPLICATE KEY UPDATE nombre = 'ocio';

UPDATE eventos
   SET categoria_id = CASE LOWER(TRIM(categoria))
     WHEN 'trabajo' THEN 1
     WHEN 'personal' THEN 2
     WHEN 'estudio' THEN 3
     WHEN 'deporte' THEN 4
     WHEN 'ocio' THEN 5
     WHEN 'ocio / deporte' THEN 4
   END;

ALTER TABLE eventos
  MODIFY categoria_id INT NOT NULL,
  ADD CONSTRAINT fk_eventos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  DROP COLUMN categoria;
