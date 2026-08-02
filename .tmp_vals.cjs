const xlsx = require("xlsx");
const p = "sql/29.07 EMO - CONSORCIO LOS ANDES(REG) (1).xlsx";
const wb = xlsx.readFile(p);
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

const lines = [];
for (const r of data) {
  const dni = String(r[3] || "").trim();
  const puesto = String(r[4] || "").trim().replace(/\s+/g, " ");
  const fingRaw = r[5];
  const fprogRaw = r[12];
  const fecha = excelToYmd(fingRaw) || excelToYmd(fprogRaw);
  lines.push(`('${dni.replace(/'/g, "''")}', '${puesto.replace(/'/g, "''")}', 'GENERAL', '${fecha}')`);
}
console.log(lines.join(",\n"));
