-- Add legacy-aligned fields to empresas_ocupacionales (idempotent)
-- Run this after 20260723_0004 on the selected occupational database.

SET @db_name = DATABASE();

-- nombre_comercial
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.columns
      WHERE table_schema = @db_name
        AND table_name = 'empresas_ocupacionales'
        AND column_name = 'nombre_comercial'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN nombre_comercial VARCHAR(200) NULL AFTER razon_social'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- actividad
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'actividad'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN actividad VARCHAR(200) NULL AFTER nombre_comercial'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ubicacion
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'departamento'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN departamento VARCHAR(120) NULL AFTER direccion'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'provincia'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN provincia VARCHAR(120) NULL AFTER departamento'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'distrito'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN distrito VARCHAR(120) NULL AFTER provincia'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- telefonos y contactos
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'telefono_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_1 VARCHAR(30) NULL AFTER telefono'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'telefono_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_2 VARCHAR(30) NULL AFTER telefono_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'contacto_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_1 VARCHAR(160) NULL AFTER telefono_2'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'contacto_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_2 VARCHAR(160) NULL AFTER contacto_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- correos
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'correo_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_1 VARCHAR(120) NULL AFTER correo'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'correo_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_2 VARCHAR(120) NULL AFTER correo_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- credenciales
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'rrhh_usuario'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_usuario VARCHAR(80) NULL AFTER correo_2'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'rrhh_password'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_password VARCHAR(120) NULL AFTER rrhh_usuario'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'doctor_usuario'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_usuario VARCHAR(80) NULL AFTER rrhh_password'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'doctor_password'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_password VARCHAR(120) NULL AFTER doctor_usuario'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- formatos y observacion
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'formato_principal'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_principal VARCHAR(40) NULL AFTER doctor_password'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'formato_certificado'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_certificado VARCHAR(40) NULL AFTER formato_principal'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'observacion'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN observacion TEXT NULL AFTER formato_certificado'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill canonical columns from the new primary fields
UPDATE empresas_ocupacionales
SET
  telefono = COALESCE(NULLIF(telefono, ''), NULLIF(telefono_1, ''), telefono),
  correo = COALESCE(NULLIF(correo, ''), NULLIF(correo_1, ''), correo),
  telefono_1 = COALESCE(NULLIF(telefono_1, ''), NULLIF(telefono, ''), telefono_1),
  correo_1 = COALESCE(NULLIF(correo_1, ''), NULLIF(correo, ''), correo_1);

SELECT
  column_name,
  data_type,
  is_nullable
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'empresas_ocupacionales'
  AND column_name IN (
    'ruc', 'razon_social', 'nombre_comercial', 'actividad', 'direccion',
    'departamento', 'provincia', 'distrito',
    'telefono', 'telefono_1', 'telefono_2',
    'contacto_1', 'contacto_2',
    'correo', 'correo_1', 'correo_2',
    'rrhh_usuario', 'rrhh_password', 'doctor_usuario', 'doctor_password',
    'formato_principal', 'formato_certificado', 'observacion'
  )
ORDER BY ordinal_position;
