-- Catalogo ubigeo base para formulario dinamico de pacientes.
-- Incluye departamento de Lima, sus provincias y distritos (provincia Lima completa).

CREATE TABLE IF NOT EXISTS departamento (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departamento_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provincia (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  departamento INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provincia_depto_nombre (departamento, nombre),
  KEY idx_provincia_departamento (departamento),
  CONSTRAINT fk_provincia_departamento FOREIGN KEY (departamento) REFERENCES departamento(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distrito (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provincia INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distrito_prov_nombre (provincia, nombre),
  KEY idx_distrito_provincia (provincia),
  CONSTRAINT fk_distrito_provincia FOREIGN KEY (provincia) REFERENCES provincia(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departamento (nombre)
SELECT 'LIMA'
WHERE NOT EXISTS (SELECT 1 FROM departamento WHERE UPPER(nombre) = 'LIMA');

SET @id_lima := (SELECT id FROM departamento WHERE UPPER(nombre) = 'LIMA' LIMIT 1);

INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'LIMA' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'LIMA');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'BARRANCA' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'BARRANCA');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'CAJATAMBO' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CAJATAMBO');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'CANTA' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CANTA');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'CAÑETE' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CAÑETE');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'HUARAL' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUARAL');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'HUAROCHIRI' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUAROCHIRI');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'HUAURA' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUAURA');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'OYON' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'OYON');
INSERT INTO provincia (departamento, nombre)
SELECT @id_lima, 'YAUYOS' WHERE NOT EXISTS (SELECT 1 FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'YAUYOS');

SET @id_prov_lima := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'LIMA' LIMIT 1);
SET @id_prov_barranca := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'BARRANCA' LIMIT 1);
SET @id_prov_cajatambo := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CAJATAMBO' LIMIT 1);
SET @id_prov_canta := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CANTA' LIMIT 1);
SET @id_prov_canete := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'CAÑETE' LIMIT 1);
SET @id_prov_huaral := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUARAL' LIMIT 1);
SET @id_prov_huarochiri := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUAROCHIRI' LIMIT 1);
SET @id_prov_huaura := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'HUAURA' LIMIT 1);
SET @id_prov_oyon := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'OYON' LIMIT 1);
SET @id_prov_yauyos := (SELECT id FROM provincia WHERE departamento = @id_lima AND UPPER(nombre) = 'YAUYOS' LIMIT 1);

-- Provincia de Lima (43 distritos)
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'ANCON' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='ANCON');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'ATE' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='ATE');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'BARRANCO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='BARRANCO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'BREÑA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='BREÑA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'CARABAYLLO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='CARABAYLLO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'CHACLACAYO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='CHACLACAYO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'CHORRILLOS' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='CHORRILLOS');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'CIENEGUILLA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='CIENEGUILLA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'COMAS' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='COMAS');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'EL AGUSTINO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='EL AGUSTINO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'INDEPENDENCIA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='INDEPENDENCIA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'JESUS MARIA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='JESUS MARIA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LA MOLINA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LA MOLINA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LA VICTORIA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LA VICTORIA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LIMA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LIMA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LINCE' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LINCE');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LOS OLIVOS' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LOS OLIVOS');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LURIGANCHO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LURIGANCHO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'LURIN' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='LURIN');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'MAGDALENA DEL MAR' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='MAGDALENA DEL MAR');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'MIRAFLORES' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='MIRAFLORES');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'PACHACAMAC' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='PACHACAMAC');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'PUCUSANA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='PUCUSANA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'PUENTE PIEDRA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='PUENTE PIEDRA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'PUNTA HERMOSA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='PUNTA HERMOSA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'PUNTA NEGRA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='PUNTA NEGRA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'RIMAC' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='RIMAC');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN BARTOLO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN BARTOLO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN BORJA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN BORJA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN ISIDRO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN ISIDRO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN JUAN DE LURIGANCHO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN JUAN DE LURIGANCHO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN JUAN DE MIRAFLORES' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN JUAN DE MIRAFLORES');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN LUIS' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN LUIS');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN MARTIN DE PORRES' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN MARTIN DE PORRES');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SAN MIGUEL' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SAN MIGUEL');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SANTA ANITA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SANTA ANITA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SANTA MARIA DEL MAR' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SANTA MARIA DEL MAR');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SANTA ROSA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SANTA ROSA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SANTIAGO DE SURCO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SANTIAGO DE SURCO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'SURQUILLO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='SURQUILLO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'VILLA EL SALVADOR' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='VILLA EL SALVADOR');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'VILLA MARIA DEL TRIUNFO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='VILLA MARIA DEL TRIUNFO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_lima, 'CHOSICA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_lima AND UPPER(nombre)='CHOSICA');

-- Capitales de otras provincias de Lima (minimo util)
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_barranca, 'BARRANCA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_barranca AND UPPER(nombre)='BARRANCA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_cajatambo, 'CAJATAMBO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_cajatambo AND UPPER(nombre)='CAJATAMBO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_canta, 'CANTA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_canta AND UPPER(nombre)='CANTA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_canete, 'SAN VICENTE DE CAÑETE' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_canete AND UPPER(nombre)='SAN VICENTE DE CAÑETE');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_huaral, 'HUARAL' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_huaral AND UPPER(nombre)='HUARAL');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_huarochiri, 'MATUCANA' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_huarochiri AND UPPER(nombre)='MATUCANA');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_huaura, 'HUACHO' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_huaura AND UPPER(nombre)='HUACHO');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_oyon, 'OYON' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_oyon AND UPPER(nombre)='OYON');
INSERT INTO distrito (provincia, nombre) SELECT @id_prov_yauyos, 'YAUYOS' WHERE NOT EXISTS (SELECT 1 FROM distrito WHERE provincia=@id_prov_yauyos AND UPPER(nombre)='YAUYOS');
