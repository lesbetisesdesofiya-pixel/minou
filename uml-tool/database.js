let db = null;

async function initDatabase() {
  const SQL = await initSqlJs({
    locateFile: file => `https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.10.3/${file}`
  });

  const saved = localStorage.getItem('umlcraft_db');
  if (saved) {
    const buf = new Uint8Array(JSON.parse(saved));
    db = new SQL.Database(buf);
  } else {
    db = new SQL.Database();
  }

  db.run(`
    CREATE TABLE IF NOT EXISTS projects (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      description TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now','localtime')),
      updated_at TEXT DEFAULT (datetime('now','localtime'))
    )
  `);

  db.run(`
    CREATE TABLE IF NOT EXISTS diagrams (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      project_id INTEGER NOT NULL,
      name TEXT NOT NULL,
      type TEXT NOT NULL CHECK(type IN ('usecase','class','activity')),
      created_at TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )
  `);

  db.run(`
    CREATE TABLE IF NOT EXISTS diagram_elements (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      diagram_id INTEGER NOT NULL,
      type TEXT NOT NULL,
      name TEXT DEFAULT '',
      x REAL DEFAULT 100,
      y REAL DEFAULT 100,
      width REAL DEFAULT 140,
      height REAL DEFAULT 80,
      properties TEXT DEFAULT '{}',
      FOREIGN KEY (diagram_id) REFERENCES diagrams(id) ON DELETE CASCADE
    )
  `);

  db.run(`
    CREATE TABLE IF NOT EXISTS diagram_connections (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      diagram_id INTEGER NOT NULL,
      source_id INTEGER NOT NULL,
      target_id INTEGER NOT NULL,
      type TEXT NOT NULL DEFAULT 'association',
      label TEXT DEFAULT '',
      properties TEXT DEFAULT '{}',
      FOREIGN KEY (diagram_id) REFERENCES diagrams(id) ON DELETE CASCADE,
      FOREIGN KEY (source_id) REFERENCES diagram_elements(id) ON DELETE CASCADE,
      FOREIGN KEY (target_id) REFERENCES diagram_elements(id) ON DELETE CASCADE
    )
  `);

  saveDatabase();
}

function saveDatabase() {
  if (!db) return;
  const data = db.export();
  const arr = Array.from(data);
  localStorage.setItem('umlcraft_db', JSON.stringify(arr));
}

function dbQuery(sql, params = []) {
  const stmt = db.prepare(sql);
  if (params.length) stmt.bind(params);
  const results = [];
  while (stmt.step()) {
    results.push(stmt.getAsObject());
  }
  stmt.free();
  return results;
}

function dbRun(sql, params = []) {
  db.run(sql, params);
  saveDatabase();
}

function dbLastId() {
  const res = dbQuery("SELECT last_insert_rowid() as id");
  return res[0]?.id;
}

function getProjects() {
  return dbQuery("SELECT * FROM projects ORDER BY updated_at DESC");
}

function getProject(id) {
  const res = dbQuery("SELECT * FROM projects WHERE id = ?", [id]);
  return res[0] || null;
}

function createProjectDB(name, description) {
  dbRun("INSERT INTO projects (name, description) VALUES (?, ?)", [name, description || '']);
  return dbLastId();
}

function updateProjectDB(id, name, description) {
  dbRun("UPDATE projects SET name = ?, description = ?, updated_at = datetime('now','localtime') WHERE id = ?", [name, description, id]);
}

function deleteProjectDB(id) {
  dbRun("DELETE FROM diagrams WHERE project_id = ?", [id]);
  dbRun("DELETE FROM projects WHERE id = ?", [id]);
}

function getDiagrams(projectId) {
  return dbQuery("SELECT * FROM diagrams WHERE project_id = ? ORDER BY created_at DESC", [projectId]);
}

function getDiagram(id) {
  const res = dbQuery("SELECT * FROM diagrams WHERE id = ?", [id]);
  return res[0] || null;
}

function createDiagramDB(projectId, name, type) {
  dbRun("INSERT INTO diagrams (project_id, name, type) VALUES (?, ?, ?)", [projectId, name, type]);
  return dbLastId();
}

function deleteDiagramDB(id) {
  dbRun("DELETE FROM diagram_connections WHERE diagram_id = ?", [id]);
  dbRun("DELETE FROM diagram_elements WHERE diagram_id = ?", [id]);
  dbRun("DELETE FROM diagrams WHERE id = ?", [id]);
}

function getElements(diagramId) {
  const rows = dbQuery("SELECT * FROM diagram_elements WHERE diagram_id = ? ORDER BY id", [diagramId]);
  return rows.map(r => ({ ...r, properties: JSON.parse(r.properties || '{}') }));
}

function getElement(id) {
  const res = dbQuery("SELECT * FROM diagram_elements WHERE id = ?", [id]);
  if (!res[0]) return null;
  return { ...res[0], properties: JSON.parse(res[0].properties || '{}') };
}

function createElementDB(diagramId, type, name, x, y, width, height, properties = {}) {
  dbRun(
    "INSERT INTO diagram_elements (diagram_id, type, name, x, y, width, height, properties) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    [diagramId, type, name, x, y, width || 140, height || 80, JSON.stringify(properties)]
  );
  return dbLastId();
}

function updateElementDB(id, data) {
  const fields = [];
  const values = [];
  if (data.name !== undefined) { fields.push('name = ?'); values.push(data.name); }
  if (data.x !== undefined) { fields.push('x = ?'); values.push(data.x); }
  if (data.y !== undefined) { fields.push('y = ?'); values.push(data.y); }
  if (data.width !== undefined) { fields.push('width = ?'); values.push(data.width); }
  if (data.height !== undefined) { fields.push('height = ?'); values.push(data.height); }
  if (data.properties !== undefined) { fields.push('properties = ?'); values.push(JSON.stringify(data.properties)); }
  if (fields.length === 0) return;
  values.push(id);
  dbRun(`UPDATE diagram_elements SET ${fields.join(', ')} WHERE id = ?`, values);
}

function deleteElementDB(id) {
  dbRun("DELETE FROM diagram_connections WHERE source_id = ? OR target_id = ?", [id, id]);
  dbRun("DELETE FROM diagram_elements WHERE id = ?", [id]);
}

function getConnections(diagramId) {
  const rows = dbQuery("SELECT * FROM diagram_connections WHERE diagram_id = ? ORDER BY id", [diagramId]);
  return rows.map(r => ({ ...r, properties: JSON.parse(r.properties || '{}') }));
}

function createConnectionDB(diagramId, sourceId, targetId, type, label = '', properties = {}) {
  dbRun(
    "INSERT INTO diagram_connections (diagram_id, source_id, target_id, type, label, properties) VALUES (?, ?, ?, ?, ?, ?)",
    [diagramId, sourceId, targetId, type, label, JSON.stringify(properties)]
  );
  return dbLastId();
}

function updateConnectionDB(id, data) {
  const fields = [];
  const values = [];
  if (data.type !== undefined) { fields.push('type = ?'); values.push(data.type); }
  if (data.label !== undefined) { fields.push('label = ?'); values.push(data.label); }
  if (data.properties !== undefined) { fields.push('properties = ?'); values.push(JSON.stringify(data.properties)); }
  if (fields.length === 0) return;
  values.push(id);
  dbRun(`UPDATE diagram_connections SET ${fields.join(', ')} WHERE id = ?`, values);
}

function deleteConnectionDB(id) {
  dbRun("DELETE FROM diagram_connections WHERE id = ?", [id]);
}

function exportAllData() {
  return dbQuery("SELECT * FROM projects")
    .map(p => ({
      ...p,
      diagrams: getDiagrams(p.id).map(d => ({
        ...d,
        elements: getElements(d.id),
        connections: getConnections(d.id)
      }))
    }));
}

function importAllData(data) {
  for (const p of data) {
    const newId = createProjectDB(p.name, p.description);
    for (const d of p.diagrams || []) {
      const dId = createDiagramDB(newId, d.name, d.type);
      const idMap = {};
      for (const el of d.elements || []) {
        const newElId = createElementDB(dId, el.type, el.name, el.x, el.y, el.width, el.height, el.properties || {});
        idMap[el.id] = newElId;
      }
      for (const conn of d.connections || []) {
        const src = idMap[conn.source_id] || conn.source_id;
        const tgt = idMap[conn.target_id] || conn.target_id;
        createConnectionDB(dId, src, tgt, conn.type, conn.label || '', conn.properties || {});
      }
    }
  }
  saveDatabase();
}
