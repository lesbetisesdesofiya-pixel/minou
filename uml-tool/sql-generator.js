function generateSQLFromClasses(elements, connections, dialect = 'mysql') {
  const classes = elements.filter(e => e.type === 'class');
  if (classes.length === 0) return '-- Aucune classe trouvée dans le diagramme de classes.\n';

  const inheritanceLinks = connections.filter(c => c.type === 'inheritance');
  const aggregationLinks = connections.filter(c => c.type === 'aggregation' || c.type === 'composition');
  const associationLinks = connections.filter(c => c.type === 'association');

  let sql = '';
  const tables = [];

  const typeMap = {
    mysql: {
      'String': 'VARCHAR(255)', 'Integer': 'INT', 'int': 'INT', 'Float': 'DECIMAL(10,2)',
      'Double': 'DECIMAL(10,2)', 'Boolean': 'TINYINT(1)', 'boolean': 'TINYINT(1)',
      'Date': 'DATE', 'DateTime': 'DATETIME', 'Timestamp': 'TIMESTAMP',
      'Text': 'TEXT', 'LongText': 'LONGTEXT', 'Decimal': 'DECIMAL(10,2)',
      'UUID': 'CHAR(36)', 'Blob': 'LONGBLOB', 'Time': 'TIME',
    },
    postgresql: {
      'String': 'VARCHAR(255)', 'Integer': 'INTEGER', 'int': 'INTEGER', 'Float': 'DECIMAL(10,2)',
      'Double': 'DECIMAL(10,2)', 'Boolean': 'BOOLEAN', 'boolean': 'BOOLEAN',
      'Date': 'DATE', 'DateTime': 'TIMESTAMP', 'Timestamp': 'TIMESTAMP',
      'Text': 'TEXT', 'LongText': 'TEXT', 'Decimal': 'DECIMAL(10,2)',
      'UUID': 'UUID', 'Blob': 'BYTEA', 'Time': 'TIME',
    },
    sqlite: {
      'String': 'TEXT', 'Integer': 'INTEGER', 'int': 'INTEGER', 'Float': 'REAL',
      'Double': 'REAL', 'Boolean': 'INTEGER', 'boolean': 'INTEGER',
      'Date': 'TEXT', 'DateTime': 'TEXT', 'Timestamp': 'TEXT',
      'Text': 'TEXT', 'LongText': 'TEXT', 'Decimal': 'REAL',
      'UUID': 'TEXT', 'Blob': 'BLOB', 'Time': 'TEXT',
    }
  };

  const mapType = (type) => {
    const m = typeMap[dialect] || typeMap.mysql;
    return m[type] || m['String'];
  };

  const toTableName = (name) => name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
  const toColName = (name) => name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');

  const inheritedParents = new Map();
  for (const link of inheritanceLinks) {
    inheritedParents.set(link.target_id, link.source_id);
  }

  const fkMap = new Map();
  for (const link of aggregationLinks) {
    const src = classes.find(c => c.id === link.source_id);
    const tgt = classes.find(c => c.id === link.target_id);
    if (src && tgt) {
      const key = link.type === 'composition' ? 'composition' : 'aggregation';
      if (!fkMap.has(tgt.id)) fkMap.set(tgt.id, []);
      fkMap.get(tgt.id).push({ fromTable: toTableName(src.name), fromClass: src.name, type: key });
    }
  }

  const assocMap = new Map();
  for (const link of associationLinks) {
    const src = classes.find(c => c.id === link.source_id);
    const tgt = classes.find(c => c.id === link.target_id);
    if (src && tgt) {
      if (!assocMap.has(src.id)) assocMap.set(src.id, []);
      assocMap.get(src.id).push({ targetTable: toTableName(tgt.name), targetClass: tgt.name, props: link.properties });
    }
  }

  for (const cls of classes) {
    const tableName = toTableName(cls.name);
    const props = cls.properties || {};
    const attrs = props.attributes || [];
    const isChild = inheritedParents.has(cls.id);
    const parentId = inheritedParents.get(cls.id);
    const parentClass = parentId ? classes.find(c => c.id === parentId) : null;

    const columns = [];

    if (dialect === 'mysql') {
      columns.push('    `id` INT AUTO_INCREMENT PRIMARY KEY');
    } else if (dialect === 'postgresql') {
      columns.push('    id SERIAL PRIMARY KEY');
    } else {
      columns.push('    id INTEGER PRIMARY KEY AUTOINCREMENT');
    }

    if (isChild && parentClass) {
      const parentTable = toTableName(parentClass.name);
      const pkType = dialect === 'sqlite' ? 'INTEGER' : dialect === 'postgresql' ? 'INTEGER' : 'INT';
      columns.push(`    ${parentTable}_id ${pkType}${dialect === 'mysql' ? '' : ''} NOT NULL`);
    }

    for (const attr of attrs) {
      const colName = toColName(attr.name);
      if (colName === 'id') continue;
      const colType = mapType(attr.type || 'String');
      let colDef = `    \`${colName}\` ${colType}`;
      if (attr.type === 'String' && dialect === 'mysql') colDef = `    \`${colName}\` ${colType}`;
      if (attr.type === 'Text') colDef = `    \`${colName}\` ${colType}`;
      columns.push(colDef);
    }

    const fks = fkMap.get(cls.id) || [];
    for (const fk of fks) {
      const fkColName = fk.fromTable + '_id';
      columns.push(`    \`${fkColName}\` ${dialect === 'sqlite' ? 'INTEGER' : 'INT'}`);
    }

    const assocs = assocMap.get(cls.id) || [];
    for (const assoc of assocs) {
      const ms = assoc.props?.multiplicity;
      if (ms && (ms.target === '0..*' || ms.target === '1..*' || ms.target === '*')) {
        continue;
      }
      const fkColName = assoc.targetTable + '_id';
      if (!columns.some(c => c.includes(fkColName))) {
        columns.push(`    \`${fkColName}\` ${dialect === 'sqlite' ? 'INTEGER' : 'INT'}`);
      }
    }

    if (dialect === 'mysql') {
      columns.push('    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
      columns.push('    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    } else if (dialect === 'postgresql') {
      columns.push('    created_at TIMESTAMP DEFAULT NOW()');
      columns.push('    updated_at TIMESTAMP DEFAULT NOW()');
    } else {
      columns.push('    `created_at` TEXT DEFAULT (datetime(\'now\',\'localtime\'))');
      columns.push('    `updated_at` TEXT DEFAULT (datetime(\'now\',\'localtime\'))');
    }

    let tableSQL = `CREATE TABLE IF NOT EXISTS \`${tableName}\` (\n`;
    tableSQL += columns.join(',\n');

    for (const fk of fks) {
      tableSQL += `,\n    FOREIGN KEY (\`${fk.fromTable}_id\`) REFERENCES \`${fk.fromTable}\`(id)`;
      if (fk.type === 'composition') {
        tableSQL += ' ON DELETE CASCADE';
      }
    }

    const childFks = fkMap.get(cls.id) || [];
    for (const fk of childFks) {
      tableSQL += `,\n    FOREIGN KEY (\`${fk.fromTable}_id\`) REFERENCES \`${fk.fromTable}\`(id)`;
      if (fk.type === 'composition') {
        tableSQL += ' ON DELETE CASCADE';
      }
    }

    tableSQL += '\n);\n';
    tables.push(tableSQL);
  }

  if (dialect === 'mysql') {
    sql = '-- ══════════════════════════════════════════\n';
    sql += '-- Généré par UMLCraft — MySQL\n';
    sql += '-- ══════════════════════════════════════════\n\n';
  } else if (dialect === 'postgresql') {
    sql = '-- ══════════════════════════════════════════\n';
    sql += '-- Généré par UMLCraft — PostgreSQL\n';
    sql += '-- ══════════════════════════════════════════\n\n';
  } else {
    sql = '-- ══════════════════════════════════════════\n';
    sql += '-- Généré par UMLCraft — SQLite\n';
    sql += '-- ══════════════════════════════════════════\n\n';
  }

  sql += tables.join('\n');
  return sql;
}
