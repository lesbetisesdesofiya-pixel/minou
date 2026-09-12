let editor;
let currentProject = null;
let currentDiagramId = null;
let sqlDialect = 'mysql';

async function initApp() {
  await initDatabase();
  editor = new DiagramEditor(document.getElementById('diagramCanvas'));

  document.addEventListener('click', () => {
    document.getElementById('contextMenu').classList.remove('show');
  });

  renderProjects();

  setTimeout(() => {
    document.getElementById('splash').classList.add('hidden');
    document.getElementById('app').style.display = 'flex';
    editor.resize();
  }, 800);

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
}

function notify(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `notification ${type}`;
  el.textContent = msg;
  document.getElementById('notifications').appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

function showModal(id) {
  document.getElementById(id).classList.add('show');
  const input = document.getElementById(id).querySelector('input, textarea');
  if (input) setTimeout(() => input.focus(), 100);
}

function closeModal(id) {
  document.getElementById(id).classList.remove('show');
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function toggleProperties() {
  document.getElementById('propertiesPanel').classList.toggle('collapsed');
}

function showNewProjectModal() {
  document.getElementById('projectName').value = '';
  document.getElementById('projectDesc').value = '';
  showModal('newProjectModal');
}

function createProject() {
  const name = document.getElementById('projectName').value.trim();
  if (!name) { notify('Entrez un nom', 'error'); return; }
  const desc = document.getElementById('projectDesc').value.trim();
  const id = createProjectDB(name, desc);
  closeModal('newProjectModal');
  selectProject(id);
  notify('Projet créé !', 'success');
}

function editProject(id, e) {
  e.stopPropagation();
  const p = getProject(id);
  if (!p) return;
  const name = prompt('Nom du projet:', p.name);
  if (name === null) return;
  const desc = prompt('Description:', p.description);
  if (desc === null) return;
  updateProjectDB(id, name || p.name, desc ?? p.description);
  renderProjects();
  if (currentProject?.id === id) {
    currentProject = getProject(id);
    document.getElementById('projectName')?.textContent && renderProjects();
  }
  notify('Projet mis à jour', 'success');
}

function deleteProject(id, e) {
  e.stopPropagation();
  if (!confirm('Supprimer ce projet et tous ses diagrammes ?')) return;
  deleteProjectDB(id);
  if (currentProject?.id === id) {
    currentProject = null;
    currentDiagramId = null;
    editor.clear();
    document.getElementById('toolbox').style.display = 'none';
    document.getElementById('zoomControls').style.display = 'none';
    document.getElementById('diagramTabs').innerHTML = '';
    document.getElementById('welcomeScreen').style.display = 'flex';
    document.getElementById('diagramCanvas').style.display = 'none';
    document.getElementById('propertiesPanel').style.display = 'none';
    showEmptyProperties();
  }
  renderProjects();
  notify('Projet supprimé', 'info');
}

function selectProject(id) {
  currentProject = getProject(id);
  renderProjects();
  renderDiagrams();
  document.getElementById('welcomeScreen').style.display = 'none';
  document.getElementById('diagramCanvas').style.display = 'none';
  document.getElementById('toolbox').style.display = 'none';
  document.getElementById('zoomControls').style.display = 'none';
  document.getElementById('propertiesPanel').style.display = 'none';
  currentDiagramId = null;
  editor.clear();
}

function renderProjects() {
  const projects = getProjects();
  const list = document.getElementById('projectList');
  if (projects.length === 0) {
    list.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.8rem;">Aucun projet</div>';
    return;
  }
  list.innerHTML = projects.map(p => `
    <div class="project-item ${currentProject?.id === p.id ? 'active' : ''}" onclick="selectProject(${p.id})">
      <span class="icon">📁</span>
      <div class="info">
        <div class="name">${escHtml(p.name)}</div>
        <div class="desc">${escHtml(p.description || 'Pas de description')}</div>
      </div>
      <div class="actions">
        <button onclick="editProject(${p.id}, event)" title="Modifier">✏️</button>
        <button onclick="deleteProject(${p.id}, event)" title="Supprimer">🗑️</button>
      </div>
    </div>
  `).join('');
}

function renderDiagrams() {
  if (!currentProject) return;
  const diagrams = getDiagrams(currentProject.id);
  const tabs = document.getElementById('diagramTabs');
  tabs.innerHTML = diagrams.map(d => `
    <div class="diagram-tab ${currentDiagramId === d.id ? 'active' : ''}" onclick="openDiagram(${d.id})">
      <span>${d.type === 'usecase' ? '🎭' : d.type === 'class' ? '📦' : '🔄'}</span>
      <span>${escHtml(d.name)}</span>
      <span class="close" onclick="deleteDiagramConfirm(${d.id}, event)">✕</span>
    </div>
  `).join('') + `
    <button class="diagram-tab" onclick="showNewDiagramModal()" style="opacity:0.6">+ Nouveau</button>
  `;
}

function showNewDiagramModal() {
  if (!currentProject) { notify('Sélectionnez un projet d\'abord', 'error'); return; }
  document.getElementById('diagramName').value = '';
  showModal('newDiagramModal');
}

function createDiagram() {
  const name = document.getElementById('diagramName').value.trim();
  const type = document.getElementById('diagramType').value;
  if (!name) { notify('Entrez un nom', 'error'); return; }
  const id = createDiagramDB(currentProject.id, name, type);
  closeModal('newDiagramModal');
  openDiagram(id);
  notify('Diagramme créé !', 'success');
}

function openDiagram(id) {
  currentDiagramId = id;
  const diagram = getDiagram(id);
  if (!diagram) return;

  document.getElementById('welcomeScreen').style.display = 'none';
  document.getElementById('diagramCanvas').style.display = 'block';
  document.getElementById('zoomControls').style.display = 'flex';

  editor.zoomReset();
  editor.loadDiagram(id);
  buildToolbox(diagram.type);
  renderDiagrams();
  showEmptyProperties();

  document.getElementById('propertiesPanel').style.display = 'flex';
  setTimeout(() => editor.resize(), 50);
}

function deleteDiagramConfirm(id, e) {
  e.stopPropagation();
  if (!confirm('Supprimer ce diagramme ?')) return;
  deleteDiagramDB(id);
  if (currentDiagramId === id) {
    currentDiagramId = null;
    editor.clear();
    document.getElementById('diagramCanvas').style.display = 'none';
    document.getElementById('toolbox').style.display = 'none';
    document.getElementById('zoomControls').style.display = 'none';
    showEmptyProperties();
  }
  renderDiagrams();
  notify('Diagramme supprimé', 'info');
}

function buildToolbox(type) {
  const tb = document.getElementById('toolbox');
  tb.style.display = 'flex';
  let tools = '';

  tools += `<button class="tool-btn active" data-tool="select" data-tooltip="Sélection" onclick="setTool('select')">🖱️</button>`;
  tools += `<div class="tool-separator"></div>`;

  if (type === 'usecase') {
    tools += `<button class="tool-btn" data-tool="actor" data-tooltip="Acteur" onclick="setTool('actor')">🧍</button>`;
    tools += `<button class="tool-btn" data-tool="usecase" data-tooltip="Cas d'utilisation" onclick="setTool('usecase')">⬭</button>`;
  } else if (type === 'class') {
    tools += `<button class="tool-btn" data-tool="class" data-tooltip="Classe" onclick="setTool('class')">📦</button>`;
  } else if (type === 'activity') {
    tools += `<button class="tool-btn" data-tool="start" data-tooltip="Début" onclick="setTool('start')">⚫</button>`;
    tools += `<button class="tool-btn" data-tool="action" data-tooltip="Action" onclick="setTool('action')">▭</button>`;
    tools += `<button class="tool-btn" data-tool="decision" data-tooltip="Décision" onclick="setTool('decision')">◆</button>`;
    tools += `<button class="tool-btn" data-tool="merge" data-tooltip="Fusion" onclick="setTool('merge')">◆</button>`;
    tools += `<button class="tool-btn" data-tool="end" data-tooltip="Fin" onclick="setTool('end')">⊘</button>`;
  }

  tools += `<div class="tool-separator"></div>`;
  tools += `<button class="tool-btn" data-tool="connect" data-tooltip="Connexion" onclick="setTool('connect')">🔗</button>`;

  if (type !== 'usecase') {
    const connTypes = ['association', 'inheritance', 'aggregation', 'composition', 'transition'];
    tools += `<div class="tool-separator"></div>`;
    for (const ct of connTypes) {
      const labels = { association: '⟶', inheritance: '△', aggregation: '◇', composition: '◆', transition: '→' };
      const tips = { association: 'Association', inheritance: 'Héritage', aggregation: 'Agrégation', composition: 'Composition', transition: 'Transition' };
      tools += `<button class="tool-btn" data-tool="conn-${ct}" data-tooltip="${tips[ct]}" onclick="setConnectionType('${ct}')" style="font-size:0.9rem">${labels[ct]}</button>`;
    }
  }

  tb.innerHTML = tools;
}

function setTool(tool) {
  editor.currentTool = tool;
  updateToolButtons();
  editor.canvas.style.cursor = tool === 'connect' ? 'crosshair' : tool === 'select' ? 'default' : 'crosshair';
}

function setConnectionType(type) {
  editor.connectionType = type;
  editor.currentTool = 'connect';
  updateToolButtons();
  editor.canvas.style.cursor = 'crosshair';
  notify(`Type de connexion: ${type}`, 'info');
}

function updateToolButtons() {
  document.querySelectorAll('.tool-btn').forEach(btn => {
    const t = btn.dataset.tool;
    if (t === 'select') btn.classList.toggle('active', editor.currentTool === 'select');
    else if (t === 'connect') btn.classList.toggle('active', editor.currentTool === 'connect');
    else if (t.startsWith('conn-')) {
      btn.classList.toggle('active', editor.currentTool === 'connect' && editor.connectionType === t.replace('conn-', ''));
    } else {
      btn.classList.toggle('active', editor.currentTool === t);
    }
  });
}

function showProperties(el) {
  const panel = document.getElementById('propertiesPanel');
  const content = document.getElementById('propertiesContent');
  const title = document.getElementById('panelTitle');
  panel.style.display = 'flex';

  const typeLabels = {
    actor: '🧍 Acteur', usecase: '⬭ Cas d\'utilisation', class: '📦 Classe',
    action: '▭ Action', decision: '◆ Décision', start: '⚫ Début', end: '⊘ Fin', merge: '◆ Fusion'
  };
  title.textContent = typeLabels[el.type] || el.type;

  let html = `
    <div class="prop-group">
      <div class="prop-group-title">Général</div>
      <div class="prop-field">
        <label>Nom</label>
        <input type="text" value="${escHtml(el.name)}" onchange="updateProp(${el.id}, 'name', this.value)">
      </div>
      <div class="prop-field">
        <label>X</label>
        <input type="number" value="${Math.round(el.x)}" onchange="updateProp(${el.id}, 'x', +this.value)">
      </div>
      <div class="prop-field">
        <label>Y</label>
        <input type="number" value="${Math.round(el.y)}" onchange="updateProp(${el.id}, 'y', +this.value)">
      </div>
      <div class="prop-field">
        <label>Largeur</label>
        <input type="number" value="${Math.round(el.width)}" onchange="updateProp(${el.id}, 'width', +this.value)">
      </div>
      <div class="prop-field">
        <label>Hauteur</label>
        <input type="number" value="${Math.round(el.height)}" onchange="updateProp(${el.id}, 'height', +this.value)">
      </div>
    </div>
  `;

  if (el.type === 'class') {
    const props = el.properties || {};
    const stereotype = props.stereotype || '';
    const attrs = props.attributes || [];
    const methods = props.methods || [];

    html += `
      <div class="prop-group">
        <div class="prop-group-title">Classe</div>
        <div class="prop-field">
          <label>Stereotype</label>
          <input type="text" value="${escHtml(stereotype)}" onchange="updateClassProp(${el.id}, 'stereotype', this.value)" placeholder="entity, service...">
        </div>
      </div>
      <div class="prop-group">
        <div class="prop-group-title">Attributs</div>
        <ul class="attr-list" id="attrList">
          ${attrs.map((a, i) => `
            <li class="attr-item">
              <select onchange="updateAttr(${el.id}, ${i}, 'visibility', this.value)">
                <option value="+" ${a.visibility === '+' ? 'selected' : ''}>+</option>
                <option value="-" ${a.visibility === '-' ? 'selected' : ''}>-</option>
                <option value="#" ${a.visibility === '#' ? 'selected' : ''}>#</option>
                <option value="~" ${a.visibility === '~' ? 'selected' : ''}>~</option>
              </select>
              <input type="text" value="${escHtml(a.name)}" onchange="updateAttr(${el.id}, ${i}, 'name', this.value)" placeholder="nom">
              <input type="text" value="${escHtml(a.type)}" onchange="updateAttr(${el.id}, ${i}, 'type', this.value)" placeholder="type" style="max-width:60px">
              <button class="remove" onclick="removeAttr(${el.id}, ${i})">✕</button>
            </li>
          `).join('')}
        </ul>
        <button class="add-btn" onclick="addAttr(${el.id})">+ Attribut</button>
      </div>
      <div class="prop-group">
        <div class="prop-group-title">Méthodes</div>
        <ul class="method-list" id="methodList">
          ${methods.map((m, i) => `
            <li class="method-item">
              <select onchange="updateMethod(${el.id}, ${i}, 'visibility', this.value)">
                <option value="+" ${m.visibility === '+' ? 'selected' : ''}>+</option>
                <option value="-" ${m.visibility === '-' ? 'selected' : ''}>-</option>
                <option value="#" ${m.visibility === '#' ? 'selected' : ''}>#</option>
                <option value="~" ${m.visibility === '~' ? 'selected' : ''}>~</option>
              </select>
              <input type="text" value="${escHtml(m.name)}" onchange="updateMethod(${el.id}, ${i}, 'name', this.value)" placeholder="nom">
              <input type="text" value="${escHtml(m.params || '')}" onchange="updateMethod(${el.id}, ${i}, 'params', this.value)" placeholder="params" style="max-width:50px">
              <input type="text" value="${escHtml(m.returnType || 'void')}" onchange="updateMethod(${el.id}, ${i}, 'returnType', this.value)" placeholder="retour" style="max-width:50px">
              <button class="remove" onclick="removeMethod(${el.id}, ${i})">✕</button>
            </li>
          `).join('')}
        </ul>
        <button class="add-btn" onclick="addMethod(${el.id})">+ Méthode</button>
      </div>
    `;
  }

  content.innerHTML = html;
}

function showConnectionProperties(conn) {
  const panel = document.getElementById('propertiesPanel');
  const content = document.getElementById('propertiesContent');
  const title = document.getElementById('panelTitle');
  panel.style.display = 'flex';

  const typeLabels = { association: 'Association', inheritance: 'Héritage', aggregation: 'Agrégation', composition: 'Composition', include: 'Include', extend: 'Extend', transition: 'Transition' };
  title.textContent = `🔗 ${typeLabels[conn.type] || conn.type}`;

  const props = conn.properties || {};
  const ms = props.multiplicity || {};

  content.innerHTML = `
    <div class="prop-group">
      <div class="prop-group-title">Connexion</div>
      <div class="prop-field">
        <label>Type</label>
        <select onchange="updateConnProp(${conn.id}, 'type', this.value)">
          <option value="association" ${conn.type === 'association' ? 'selected' : ''}>Association</option>
          <option value="inheritance" ${conn.type === 'inheritance' ? 'selected' : ''}>Héritage</option>
          <option value="aggregation" ${conn.type === 'aggregation' ? 'selected' : ''}>Agrégation</option>
          <option value="composition" ${conn.type === 'composition' ? 'selected' : ''}>Composition</option>
          <option value="include" ${conn.type === 'include' ? 'selected' : ''}>Include</option>
          <option value="extend" ${conn.type === 'extend' ? 'selected' : ''}>Extend</option>
          <option value="transition" ${conn.type === 'transition' ? 'selected' : ''}>Transition</option>
        </select>
      </div>
      <div class="prop-field">
        <label>Libellé</label>
        <input type="text" value="${escHtml(conn.label || '')}" onchange="updateConnLabel(${conn.id}, this.value)">
      </div>
    </div>
    <div class="prop-group">
      <div class="prop-group-title">Multiplicité</div>
      <div class="prop-field">
        <label>Source</label>
        <select onchange="updateMultiplicity(${conn.id}, 'source', this.value)">
          <option value="">—</option>
          <option value="1" ${ms.source === '1' ? 'selected' : ''}>1</option>
          <option value="0..1" ${ms.source === '0..1' ? 'selected' : ''}>0..1</option>
          <option value="0..*" ${ms.source === '0..*' ? 'selected' : ''}>0..*</option>
          <option value="1..*" ${ms.source === '1..*' ? 'selected' : ''}>1..*</option>
          <option value="*" ${ms.source === '*' ? 'selected' : ''}>*</option>
        </select>
      </div>
      <div class="prop-field">
        <label>Cible</label>
        <select onchange="updateMultiplicity(${conn.id}, 'target', this.value)">
          <option value="">—</option>
          <option value="1" ${ms.target === '1' ? 'selected' : ''}>1</option>
          <option value="0..1" ${ms.target === '0..1' ? 'selected' : ''}>0..1</option>
          <option value="0..*" ${ms.target === '0..*' ? 'selected' : ''}>0..*</option>
          <option value="1..*" ${ms.target === '1..*' ? 'selected' : ''}>1..*</option>
          <option value="*" ${ms.target === '*' ? 'selected' : ''}>*</option>
        </select>
      </div>
    </div>
    <div style="margin-top:16px">
      <button class="btn btn-danger btn-sm" onclick="deleteConnFromPanel(${conn.id})">🗑️ Supprimer</button>
    </div>
  `;
}

function showEmptyProperties() {
  document.getElementById('propertiesContent').innerHTML = `
    <div class="empty-state">
      <div class="icon">🔍</div>
      <h3>Aucune sélection</h3>
      <p>Sélectionnez un élément pour voir ses propriétés</p>
    </div>
  `;
  document.getElementById('panelTitle').textContent = 'Propriétés';
}

function updateProp(id, key, value) {
  updateElementDB(id, { [key]: value });
  const el = getElement(id);
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = el;
  editor.draw();
  saveDatabase();
}

function updateClassProp(id, key, value) {
  const el = getElement(id);
  const props = el.properties;
  props[key] = value;
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  editor.draw();
  saveDatabase();
}

function addAttr(id) {
  const el = getElement(id);
  const props = el.properties;
  if (!props.attributes) props.attributes = [];
  props.attributes.push({ name: 'nouvel_attribut', type: 'String', visibility: '+' });
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  showProperties(editor.elements[idx]);
  editor.draw();
  saveDatabase();
}

function updateAttr(id, index, key, value) {
  const el = getElement(id);
  const props = el.properties;
  props.attributes[index][key] = value;
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  editor.draw();
  saveDatabase();
}

function removeAttr(id, index) {
  const el = getElement(id);
  const props = el.properties;
  props.attributes.splice(index, 1);
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  showProperties(editor.elements[idx]);
  editor.draw();
  saveDatabase();
}

function addMethod(id) {
  const el = getElement(id);
  const props = el.properties;
  if (!props.methods) props.methods = [];
  props.methods.push({ name: 'nouvelle_methode', returnType: 'void', params: '', visibility: '+' });
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  showProperties(editor.elements[idx]);
  editor.draw();
  saveDatabase();
}

function updateMethod(id, index, key, value) {
  const el = getElement(id);
  const props = el.properties;
  props.methods[index][key] = value;
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  editor.draw();
  saveDatabase();
}

function removeMethod(id, index) {
  const el = getElement(id);
  const props = el.properties;
  props.methods.splice(index, 1);
  updateElementDB(id, { properties: props });
  const idx = editor.elements.findIndex(e => e.id === id);
  if (idx !== -1) editor.elements[idx] = getElement(id);
  showProperties(editor.elements[idx]);
  editor.draw();
  saveDatabase();
}

function updateConnProp(id, key, value) {
  updateConnectionDB(id, { [key]: value });
  editor.loadDiagram(currentDiagramId);
  const conn = getConnections(currentDiagramId).find(c => c.id === id);
  if (conn) showConnectionProperties(conn);
  saveDatabase();
}

function updateConnLabel(id, value) {
  updateConnectionDB(id, { label: value });
  const idx = editor.connections.findIndex(c => c.id === id);
  if (idx !== -1) editor.connections[idx] = getConnections(currentDiagramId).find(c => c.id === id);
  editor.draw();
  saveDatabase();
}

function updateMultiplicity(id, side, value) {
  const idx = editor.connections.findIndex(c => c.id === id);
  if (idx === -1) return;
  const conn = editor.connections[idx];
  const props = conn.properties || {};
  if (!props.multiplicity) props.multiplicity = {};
  props.multiplicity[side] = value;
  updateConnectionDB(id, { properties: props });
  editor.connections[idx] = getConnections(currentDiagramId).find(c => c.id === id);
  editor.draw();
  saveDatabase();
}

function deleteConnFromPanel(id) {
  deleteConnectionDB(id);
  editor.loadDiagram(currentDiagramId);
  showEmptyProperties();
  notify('Connexion supprimée', 'info');
}

function generateSQL() {
  if (!currentDiagramId) {
    notify('Ouvrez un diagramme de classes d\'abord', 'error');
    return;
  }
  const diagram = getDiagram(currentDiagramId);
  if (diagram?.type !== 'class') {
    notify('La génération SQL nécessite un diagramme de classes', 'error');
    return;
  }

  const tabs = document.getElementById('sqlTabs');
  const editor_el = document.getElementById('sqlEditor');
  tabs.innerHTML = ['mysql', 'postgresql', 'sqlite'].map(d => `
    <button class="sql-tab ${d === sqlDialect ? 'active' : ''}" onclick="switchSQLDialect('${d}')">${d.charAt(0).toUpperCase() + d.slice(1)}</button>
  `).join('');

  editor_el.value = generateSQLFromClasses(editor.elements, editor.connections, sqlDialect);
  showModal('sqlModal');
}

function switchSQLDialect(d) {
  sqlDialect = d;
  document.querySelectorAll('.sql-tab').forEach(t => t.classList.toggle('active', t.textContent.toLowerCase() === d));
  document.getElementById('sqlEditor').value = generateSQLFromClasses(editor.elements, editor.connections, sqlDialect);
}

function copySQL() {
  navigator.clipboard.writeText(document.getElementById('sqlEditor').value).then(() => {
    notify('SQL copié !', 'success');
  });
}

function downloadSQL() {
  const blob = new Blob([document.getElementById('sqlEditor').value], { type: 'text/sql' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = (currentProject?.name || 'database') + '.sql';
  a.click();
  URL.revokeObjectURL(a.href);
  notify('Fichier téléchargé !', 'success');
}

function exportDiagram(format) {
  if (!currentDiagramId) { notify('Aucun diagramme ouvert', 'error'); return; }

  const tempCanvas = document.createElement('canvas');
  const elements = editor.elements;
  if (elements.length === 0) { notify('Diagramme vide', 'error'); return; }

  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
  for (const el of elements) {
    minX = Math.min(minX, el.x);
    minY = Math.min(minY, el.y);
    maxX = Math.max(maxX, el.x + el.width);
    maxY = Math.max(maxY, el.y + el.height);
  }

  const padding = 40;
  minX -= padding; minY -= padding; maxX += padding; maxY += padding;
  tempCanvas.width = maxX - minX;
  tempCanvas.height = maxY - minY;

  const tempCtx = tempCanvas.getContext('2d');
  tempCtx.fillStyle = '#1e1e2e';
  tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

  const origCtx = editor.ctx;
  const origCanvas = editor.canvas;
  editor.ctx = tempCtx;
  editor.canvas = tempCanvas;
  const origZoom = editor.zoom;
  const origPanX = editor.panX;
  const origPanY = editor.panY;
  editor.zoom = 1;
  editor.panX = -minX;
  editor.panY = -minY;
  editor.draw();

  editor.ctx = origCtx;
  editor.canvas = origCanvas;
  editor.zoom = origZoom;
  editor.panX = origPanX;
  editor.panY = origPanY;
  editor.draw();

  if (format === 'png') {
    const link = document.createElement('a');
    link.download = (getDiagram(currentDiagramId)?.name || 'diagramme') + '.png';
    link.href = tempCanvas.toDataURL('image/png');
    link.click();
    notify('PNG exporté !', 'success');
  } else if (format === 'svg') {
    const imgData = tempCanvas.toDataURL('image/png');
    const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${tempCanvas.width}" height="${tempCanvas.height}">
  <image width="${tempCanvas.width}" height="${tempCanvas.height}" xlink:href="${imgData}"/>
</svg>`;
    const blob = new Blob([svg], { type: 'image/svg+xml' });
    const link = document.createElement('a');
    link.download = (getDiagram(currentDiagramId)?.name || 'diagramme') + '.svg';
    link.href = URL.createObjectURL(blob);
    link.click();
    URL.revokeObjectURL(link.href);
    notify('SVG exporté !', 'success');
  }
}

function exportAllProjects() {
  const data = exportAllData();
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'umlcraft_export_' + new Date().toISOString().slice(0, 10) + '.json';
  a.click();
  URL.revokeObjectURL(a.href);
  notify('Projet exporté !', 'success');
}

function importProject() {
  document.getElementById('importInput').click();
}

function handleImport(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    try {
      const data = JSON.parse(ev.target.result);
      importAllData(data);
      renderProjects();
      notify('Projet importé !', 'success');
    } catch (err) {
      notify('Fichier invalide', 'error');
    }
  };
  reader.readAsText(file);
  e.target.value = '';
}

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.getElementById('newProjectModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('newProjectModal');
});
document.getElementById('newDiagramModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('newDiagramModal');
});
document.getElementById('sqlModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('sqlModal');
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModal('newProjectModal');
    closeModal('newDiagramModal');
    closeModal('sqlModal');
  }
});

initApp();
