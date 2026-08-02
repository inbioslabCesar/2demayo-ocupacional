const fs = require("fs");
const xlsx = require("xlsx");

const src = "sql/29.07 EMO - CONSORCIO LOS ANDES(REG) (1).xlsx";
const out = "sql/generar_insert_step2_consorcio_one_shot.sql";

const wb = xlsx.readFile(src);
const ws = wb.Sheets[wb.SheetNames[0]];
const rows = xlsx.utils.sheet_to_json(ws, { header: 1, defval: "" });
const data = rows.slice(3).filter(r => String(r[0] || "").trim() !== "");

function excelToYmd(v) {
  if (v === null || v === undefined || v === "") return "";
  if (typeof v === "number") {
    const d = xlsx.SSF.parse_date_code(v);
    if (!d) return "";
    const mm = String(d.m).padStart(2, "0");
    const dd = String(d.d).padStart(2, "0");
    return `${d.y}-${mm}-${dd}`;
  }
  const s = String(v).trim();
  const m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (m) {
    const dd = m[1].padStart(2, "0");
    const mm = m[2].padStart(2, "0");
    return `${m[3]}-${mm}-${dd}`;
  }
  return "";
}

const tuples = [];
for (const r of data) {
  const dni = String(r[3] || "").trim().replace(/'/g, "''");
  const puesto = String(r[4] || "").trim().replace(/\s+/g, " ").replace(/'/g, "''");
  const fecha = excelToYmd(r[5]) || excelToYmd(r[12]);
  tuples.push({ dni, puesto, area: "GENERAL", fecha });
}

const unionSql = tuples.map((t, idx) => {
  const line = `SELECT '${t.dni}' AS dni, '${t.puesto}' AS puesto_trabajo, '${t.area}' AS area_riesgo, '${t.fecha}' AS fecha_ingreso`;
  return idx === 0 ? line : `UNION ALL\n${line}`;
}).join("\n");

const sql = `-- Genera en una sola celda el INSERT completo para Step 2 (sin tablas temporales)\n-- Ejecutar en BD clinica: u330560936_bd2DeMayo\n\nSET SESSION group_concat_max_len = 1000000;\n\nSELECT CONCAT(\n  'INSERT INTO tmp_trabajadores_map (dni, external_patient_id, puesto_trabajo, area_riesgo, fecha_ingreso) VALUES\\n',\n  GROUP_CONCAT(\n    CONCAT(\n      '(''',\n      REPLACE(m.dni, '''', ''''''),\n      ''', ',\n      m.external_patient_id,\n      ', ''',\n      REPLACE(m.puesto_trabajo, '''', ''''''),\n      ''', ''',\n      REPLACE(m.area_riesgo, '''', ''''''),\n      ''', ''',\n      DATE_FORMAT(m.fecha_ingreso, '%Y-%m-%d'),\n      ''')'\n    )\n    ORDER BY m.dni\n    SEPARATOR ',\\n'\n  ),\n  ';'\n) AS sql_insert\nFROM (\n  SELECT\n    t.dni,\n    t.puesto_trabajo,\n    t.area_riesgo,\n    t.fecha_ingreso,\n    MIN(p.id) AS external_patient_id,\n    COUNT(p.id) AS coincidencias\n  FROM (\n${unionSql}\n  ) t\n  LEFT JOIN pacientes p\n    ON (\n      CONVERT(TRIM(p.dni) USING utf8mb4) COLLATE utf8mb4_general_ci\n        = CONVERT(t.dni USING utf8mb4) COLLATE utf8mb4_general_ci\n      OR (\n        TRIM(p.dni) REGEXP '^[0-9]+$'\n        AND CONVERT(LPAD(CAST(TRIM(p.dni) AS UNSIGNED), 8, '0') USING utf8mb4) COLLATE utf8mb4_general_ci\n          = CONVERT(t.dni USING utf8mb4) COLLATE utf8mb4_general_ci\n      )\n    )\n  GROUP BY t.dni, t.puesto_trabajo, t.area_riesgo, t.fecha_ingreso\n) m\nWHERE m.external_patient_id IS NOT NULL\n  AND m.coincidencias = 1;\n\n-- Diagnostico opcional de filas no mapeadas o ambiguas\nSELECT\n  m.dni,\n  m.external_patient_id,\n  m.coincidencias\nFROM (\n  SELECT\n    t.dni,\n    MIN(p.id) AS external_patient_id,\n    COUNT(p.id) AS coincidencias\n  FROM (\n${unionSql}\n  ) t\n  LEFT JOIN pacientes p\n    ON (\n      CONVERT(TRIM(p.dni) USING utf8mb4) COLLATE utf8mb4_general_ci\n        = CONVERT(t.dni USING utf8mb4) COLLATE utf8mb4_general_ci\n      OR (\n        TRIM(p.dni) REGEXP '^[0-9]+$'\n        AND CONVERT(LPAD(CAST(TRIM(p.dni) AS UNSIGNED), 8, '0') USING utf8mb4) COLLATE utf8mb4_general_ci\n          = CONVERT(t.dni USING utf8mb4) COLLATE utf8mb4_general_ci\n      )\n    )\n  GROUP BY t.dni\n) m\nWHERE m.external_patient_id IS NULL OR m.coincidencias > 1\nORDER BY m.dni;\n`;

fs.writeFileSync(out, sql, "utf8");
console.log("written", out, "rows", tuples.length);
