-- 20260730_0050_ocupacional_normalizar_textos_examenes.sql
-- Objetivo: normalizar textos historicos con mojibake en examenes ocupacionales.

START TRANSACTION;

-- Limpieza global en ocupacional_examenes_generales
UPDATE ocupacional_examenes_generales
SET
    descripcion = TRIM(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(
                                                                    REPLACE(
                                                                        REPLACE(
                                                                            REPLACE(
                                                                                REPLACE(
                                                                                    REPLACE(
                                                                                        REPLACE(
                                                                                            REPLACE(
                                                                                                REPLACE(
                                                                                                    REPLACE(
                                                                                                        REPLACE(
                                                                                                            REPLACE(
                                                                                                                COALESCE(descripcion, ''),
                                                                                                                'Ã¡', 'á'
                                                                                                            ),
                                                                                                            'Ã©', 'é'
                                                                                                        ),
                                                                                                        'Ã­', 'í'
                                                                                                    ),
                                                                                                    'Ã³', 'ó'
                                                                                                ),
                                                                                                'Ãº', 'ú'
                                                                                            ),
                                                                                            'Ã±', 'ñ'
                                                                                        ),
                                                                                        'Ã', 'Á'
                                                                                    ),
                                                                                    'Ã‰', 'É'
                                                                                ),
                                                                                'Ã', 'Í'
                                                                            ),
                                                                            'Ã“', 'Ó'
                                                                        ),
                                                                        'Ãš', 'Ú'
                                                                    ),
                                                                    'Ã‘', 'Ñ'
                                                                ),
                                                                'Ã�cido', 'Ácido'
                                                            ),
                                                            'Ã�rico', 'Úrico'
                                                        ),
                                                        'Ã�ricos', 'Úricos'
                                                    ),
                                                    'MÃ�S', 'MÁS'
                                                ),
                                                'BioquÃ­mica', 'Bioquímica'
                                            ),
                                            'UroanÃ¡lisis', 'Uroanálisis'
                                        ),
                                        'HematologÃ­a', 'Hematología'
                                    ),
                                    'InmunologÃ­a', 'Inmunología'
                                ),
                                'QuÃ­mica', 'Química'
                            ),
                            'LipÃ­dico', 'Lipídico'
                        ),
                        'Â', ''
                    ),
                    '­', ''
                ),
                '﻿', ''
            ),
            '�', ''
        )
    ),
    grupo = TRIM(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(
                                                                    REPLACE(
                                                                        REPLACE(
                                                                            REPLACE(
                                                                                REPLACE(
                                                                                    REPLACE(
                                                                                        REPLACE(
                                                                                            REPLACE(
                                                                                                REPLACE(
                                                                                                    REPLACE(
                                                                                                        REPLACE(
                                                                                                            REPLACE(
                                                                                                                COALESCE(grupo, ''),
                                                                                                                'Ã¡', 'á'
                                                                                                            ),
                                                                                                            'Ã©', 'é'
                                                                                                        ),
                                                                                                        'Ã­', 'í'
                                                                                                    ),
                                                                                                    'Ã³', 'ó'
                                                                                                ),
                                                                                                'Ãº', 'ú'
                                                                                            ),
                                                                                            'Ã±', 'ñ'
                                                                                        ),
                                                                                        'Ã', 'Á'
                                                                                    ),
                                                                                    'Ã‰', 'É'
                                                                                ),
                                                                                'Ã', 'Í'
                                                                            ),
                                                                            'Ã“', 'Ó'
                                                                        ),
                                                                        'Ãš', 'Ú'
                                                                    ),
                                                                    'Ã‘', 'Ñ'
                                                                ),
                                                                'Ã�cido', 'Ácido'
                                                            ),
                                                            'Ã�rico', 'Úrico'
                                                        ),
                                                        'Ã�ricos', 'Úricos'
                                                    ),
                                                    'MÃ�S', 'MÁS'
                                                ),
                                                'BioquÃ­mica', 'Bioquímica'
                                            ),
                                            'UroanÃ¡lisis', 'Uroanálisis'
                                        ),
                                        'HematologÃ­a', 'Hematología'
                                    ),
                                    'InmunologÃ­a', 'Inmunología'
                                ),
                                'QuÃ­mica', 'Química'
                            ),
                            'LipÃ­dico', 'Lipídico'
                        ),
                        'Â', ''
                    ),
                    '­', ''
                ),
                '﻿', ''
            ),
            '�', ''
        )
    ),
    subgrupo = TRIM(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(
                                                                    REPLACE(
                                                                        REPLACE(
                                                                            REPLACE(
                                                                                REPLACE(
                                                                                    REPLACE(
                                                                                        REPLACE(
                                                                                            REPLACE(
                                                                                                REPLACE(
                                                                                                    REPLACE(
                                                                                                        REPLACE(
                                                                                                            REPLACE(
                                                                                                                COALESCE(subgrupo, ''),
                                                                                                                'Ã¡', 'á'
                                                                                                            ),
                                                                                                            'Ã©', 'é'
                                                                                                        ),
                                                                                                        'Ã­', 'í'
                                                                                                    ),
                                                                                                    'Ã³', 'ó'
                                                                                                ),
                                                                                                'Ãº', 'ú'
                                                                                            ),
                                                                                            'Ã±', 'ñ'
                                                                                        ),
                                                                                        'Ã', 'Á'
                                                                                    ),
                                                                                    'Ã‰', 'É'
                                                                                ),
                                                                                'Ã', 'Í'
                                                                            ),
                                                                            'Ã“', 'Ó'
                                                                        ),
                                                                        'Ãš', 'Ú'
                                                                    ),
                                                                    'Ã‘', 'Ñ'
                                                                ),
                                                                'Ã�cido', 'Ácido'
                                                            ),
                                                            'Ã�rico', 'Úrico'
                                                        ),
                                                        'Ã�ricos', 'Úricos'
                                                    ),
                                                    'MÃ�S', 'MÁS'
                                                ),
                                                'BioquÃ­mica', 'Bioquímica'
                                            ),
                                            'UroanÃ¡lisis', 'Uroanálisis'
                                        ),
                                        'HematologÃ­a', 'Hematología'
                                    ),
                                    'InmunologÃ­a', 'Inmunología'
                                ),
                                'QuÃ­mica', 'Química'
                            ),
                            'LipÃ­dico', 'Lipídico'
                        ),
                        'Â', ''
                    ),
                    '­', ''
                ),
                '﻿', ''
            ),
            '�', ''
        )
    ),
    valores_normales = TRIM(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(
                                                                    REPLACE(
                                                                        REPLACE(
                                                                            REPLACE(
                                                                                REPLACE(
                                                                                    REPLACE(
                                                                                        REPLACE(
                                                                                            REPLACE(
                                                                                                REPLACE(
                                                                                                    REPLACE(
                                                                                                        REPLACE(
                                                                                                            REPLACE(
                                                                                                                COALESCE(valores_normales, ''),
                                                                                                                'Ã¡', 'á'
                                                                                                            ),
                                                                                                            'Ã©', 'é'
                                                                                                        ),
                                                                                                        'Ã­', 'í'
                                                                                                    ),
                                                                                                    'Ã³', 'ó'
                                                                                                ),
                                                                                                'Ãº', 'ú'
                                                                                            ),
                                                                                            'Ã±', 'ñ'
                                                                                        ),
                                                                                        'Ã', 'Á'
                                                                                    ),
                                                                                    'Ã‰', 'É'
                                                                                ),
                                                                                'Ã', 'Í'
                                                                            ),
                                                                            'Ã“', 'Ó'
                                                                        ),
                                                                        'Ãš', 'Ú'
                                                                    ),
                                                                    'Ã‘', 'Ñ'
                                                                ),
                                                                'Ã�cido', 'Ácido'
                                                            ),
                                                            'Ã�rico', 'Úrico'
                                                        ),
                                                        'Ã�ricos', 'Úricos'
                                                    ),
                                                    'MÃ�S', 'MÁS'
                                                ),
                                                'BioquÃ­mica', 'Bioquímica'
                                            ),
                                            'UroanÃ¡lisis', 'Uroanálisis'
                                        ),
                                        'HematologÃ­a', 'Hematología'
                                    ),
                                    'InmunologÃ­a', 'Inmunología'
                                ),
                                'QuÃ­mica', 'Química'
                            ),
                            'LipÃ­dico', 'Lipídico'
                        ),
                        'Â', ''
                    ),
                    '­', ''
                ),
                '﻿', ''
            ),
            '�', ''
        )
    )
WHERE 1 = 1;

-- Segunda pasada para variantes degradadas sin marcador de acento (ej: HematologÃa).
UPDATE ocupacional_examenes_generales
SET
    descripcion = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(descripcion, ''),
        'HematologÃa', 'Hematología'),
        'InmunologÃa', 'Inmunología'),
        'UroanÃlisis', 'Uroanálisis'),
        'BioquÃmica', 'Bioquímica'),
        'QuÃmica', 'Química'),
        'LipÃdico', 'Lipídico'),
    grupo = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(grupo, ''),
        'HematologÃa', 'Hematología'),
        'InmunologÃa', 'Inmunología'),
        'UroanÃlisis', 'Uroanálisis'),
        'BioquÃmica', 'Bioquímica'),
        'QuÃmica', 'Química'),
        'LipÃdico', 'Lipídico'),
    subgrupo = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(subgrupo, ''),
        'HematologÃa', 'Hematología'),
        'InmunologÃa', 'Inmunología'),
        'UroanÃlisis', 'Uroanálisis'),
        'BioquÃmica', 'Bioquímica'),
        'QuÃmica', 'Química'),
        'LipÃdico', 'Lipídico'),
    valores_normales = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(valores_normales, ''),
        'HematologÃa', 'Hematología'),
        'InmunologÃa', 'Inmunología'),
        'UroanÃlisis', 'Uroanálisis'),
        'BioquÃmica', 'Bioquímica'),
        'QuÃmica', 'Química'),
        'LipÃdico', 'Lipídico')
WHERE 1 = 1;

-- Tercera pasada quirurgica: secuencias binarias detectadas en historicos.
UPDATE ocupacional_examenes_generales
SET
    descripcion = REPLACE(REPLACE(COALESCE(descripcion, ''), UNHEX('C383C28361'), 'ía'), UNHEX('C383C283C2A1'), 'á'),
    grupo = REPLACE(REPLACE(COALESCE(grupo, ''), UNHEX('C383C28361'), 'ía'), UNHEX('C383C283C2A1'), 'á'),
    subgrupo = REPLACE(REPLACE(COALESCE(subgrupo, ''), UNHEX('C383C28361'), 'ía'), UNHEX('C383C283C2A1'), 'á'),
    valores_normales = REPLACE(REPLACE(COALESCE(valores_normales, ''), UNHEX('C383C28361'), 'ía'), UNHEX('C383C283C2A1'), 'á')
WHERE 1 = 1;

COMMIT;
