class DiagramEditor {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.elements = [];
    this.connections = [];
    this.selected = null;
    this.selectedConnection = null;
    this.hovered = null;
    this.dragging = false;
    this.connecting = false;
    this.connectFrom = null;
    this.connectStartX = 0;
    this.connectStartY = 0;
    this.dragOffsetX = 0;
    this.dragOffsetY = 0;
    this.zoom = 1;
    this.panX = 0;
    this.panY = 0;
    this.panning = false;
    this.panStartX = 0;
    this.panStartY = 0;
    this.currentTool = 'select';
    this.diagramId = null;
    this.diagramType = 'usecase';
    this.connectionType = 'association';
    this.selectedConnectionType = 'association';

    this.resize();
    this.bindEvents();
    window.addEventListener('resize', () => this.resize());
  }

  resize() {
    const container = this.canvas.parentElement;
    this.canvas.width = container.clientWidth;
    this.canvas.height = container.clientHeight;
    this.draw();
  }

  bindEvents() {
    this.canvas.addEventListener('mousedown', e => this.onMouseDown(e));
    this.canvas.addEventListener('mousemove', e => this.onMouseMove(e));
    this.canvas.addEventListener('mouseup', e => this.onMouseUp(e));
    this.canvas.addEventListener('wheel', e => this.onWheel(e), { passive: false });
    this.canvas.addEventListener('contextmenu', e => {
      e.preventDefault();
      this.onContextMenu(e);
    });
    this.canvas.addEventListener('dblclick', e => this.onDoubleClick(e));

    document.addEventListener('keydown', e => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
      if (e.key === 'Delete' || e.key === 'Backspace') {
        this.deleteSelected();
      }
      if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        this.duplicateSelected();
      }
      if (e.key === 'Escape') {
        this.selected = null;
        this.selectedConnection = null;
        this.connecting = false;
        this.connectFrom = null;
        this.currentTool = 'select';
        updateToolButtons();
        this.draw();
        showEmptyProperties();
      }
    });
  }

  getMousePos(e) {
    const rect = this.canvas.getBoundingClientRect();
    return {
      x: (e.clientX - rect.left - this.panX) / this.zoom,
      y: (e.clientY - rect.top - this.panY) / this.zoom
    };
  }

  onMouseDown(e) {
    const pos = this.getMousePos(e);
    document.getElementById('contextMenu').classList.remove('show');

    if (e.button === 1 || (e.button === 0 && e.altKey)) {
      this.panning = true;
      this.panStartX = e.clientX - this.panX;
      this.panStartY = e.clientY - this.panY;
      this.canvas.style.cursor = 'grabbing';
      return;
    }

    if (this.currentTool === 'select') {
      const el = this.hitTest(pos.x, pos.y);
      if (el) {
        this.selected = el;
        this.selectedConnection = null;
        this.dragging = true;
        this.dragOffsetX = pos.x - el.x;
        this.dragOffsetY = pos.y - el.y;
        showProperties(el);
      } else {
        const conn = this.hitTestConnection(pos.x, pos.y);
        if (conn) {
          this.selectedConnection = conn;
          this.selected = null;
          showConnectionProperties(conn);
        } else {
          this.selected = null;
          this.selectedConnection = null;
          showEmptyProperties();
        }
      }
    } else if (this.currentTool === 'connect') {
      const el = this.hitTest(pos.x, pos.y);
      if (el) {
        this.connecting = true;
        this.connectFrom = el;
        this.connectStartX = pos.x;
        this.connectStartY = pos.y;
      }
    } else if (['actor', 'usecase', 'class', 'action', 'decision', 'start', 'end', 'merge', 'fork', 'join', 'swimlane'].includes(this.currentTool)) {
      this.addElement(this.currentTool, pos.x, pos.y);
    }

    this.draw();
  }

  onMouseMove(e) {
    const pos = this.getMousePos(e);

    if (this.panning) {
      this.panX = e.clientX - this.panStartX;
      this.panY = e.clientY - this.panStartY;
      this.draw();
      return;
    }

    if (this.dragging && this.selected) {
      this.selected.x = pos.x - this.dragOffsetX;
      this.selected.y = pos.y - this.dragOffsetY;
      this.draw();
      return;
    }

    if (this.connecting) {
      this.draw();
      const ctx = this.ctx;
      ctx.save();
      ctx.translate(this.panX, this.panY);
      ctx.scale(this.zoom, this.zoom);
      ctx.strokeStyle = '#7c5cfc';
      ctx.lineWidth = 2 / this.zoom;
      ctx.setLineDash([6 / this.zoom, 4 / this.zoom]);
      ctx.beginPath();
      ctx.moveTo(this.connectStartX, this.connectStartY);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.restore();
      return;
    }

    const el = this.hitTest(pos.x, pos.y);
    if (el !== this.hovered) {
      this.hovered = el;
      this.canvas.style.cursor = el ? (this.currentTool === 'connect' ? 'crosshair' : 'move') : (this.currentTool === 'connect' ? 'crosshair' : 'default');
      this.draw();
    }
  }

  onMouseUp(e) {
    if (this.panning) {
      this.panning = false;
      this.canvas.style.cursor = 'default';
      return;
    }

    if (this.connecting && this.connectFrom) {
      const pos = this.getMousePos(e);
      const target = this.hitTest(pos.x, pos.y);
      if (target && target.id !== this.connectFrom.id) {
        createConnectionDB(this.diagramId, this.connectFrom.id, target.id, this.connectionType);
        this.loadDiagram(this.diagramId);
        notify('Connexion créée', 'success');
      }
      this.connecting = false;
      this.connectFrom = null;
      this.draw();
      return;
    }

    if (this.dragging && this.selected) {
      updateElementDB(this.selected.id, { x: this.selected.x, y: this.selected.y });
      saveDatabase();
    }

    this.dragging = false;
  }

  onWheel(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    const newZoom = Math.max(0.2, Math.min(3, this.zoom + delta));
    const rect = this.canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left;
    const my = e.clientY - rect.top;
    this.panX = mx - (mx - this.panX) * (newZoom / this.zoom);
    this.panY = my - (my - this.panY) * (newZoom / this.zoom);
    this.zoom = newZoom;
    document.getElementById('zoomLevel').textContent = Math.round(this.zoom * 100) + '%';
    this.draw();
  }

  onContextMenu(e) {
    const pos = this.getMousePos(e);
    const el = this.hitTest(pos.x, pos.y);
    if (el) {
      this.selected = el;
      this.selectedConnection = null;
      showProperties(el);
      this.draw();
      const menu = document.getElementById('contextMenu');
      menu.style.left = e.clientX + 'px';
      menu.style.top = e.clientY + 'px';
      menu.classList.add('show');
    }
  }

  onDoubleClick(e) {
    const pos = this.getMousePos(e);
    const el = this.hitTest(pos.x, pos.y);
    if (el) {
      this.selected = el;
      showProperties(el);
      this.draw();
    }
  }

  hitTest(x, y) {
    for (let i = this.elements.length - 1; i >= 0; i--) {
      const el = this.elements[i];
      if (x >= el.x && x <= el.x + el.width && y >= el.y && y <= el.y + el.height) {
        return el;
      }
    }
    return null;
  }

  hitTestConnection(x, y) {
    for (const conn of this.connections) {
      const src = this.elements.find(e => e.id === conn.source_id);
      const tgt = this.elements.find(e => e.id === conn.target_id);
      if (!src || !tgt) continue;

      const sx = src.x + src.width / 2;
      const sy = src.y + src.height / 2;
      const tx = tgt.x + tgt.width / 2;
      const ty = tgt.y + tgt.height / 2;

      const dist = this.pointToSegmentDistance(x, y, sx, sy, tx, ty);
      if (dist < 8) return conn;
    }
    return null;
  }

  pointToSegmentDistance(px, py, x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const lenSq = dx * dx + dy * dy;
    if (lenSq === 0) return Math.hypot(px - x1, py - y1);
    let t = Math.max(0, Math.min(1, ((px - x1) * dx + (py - y1) * dy) / lenSq));
    return Math.hypot(px - (x1 + t * dx), py - (y1 + t * dy));
  }

  addElement(type, x, y) {
    let w = 140, h = 80, name = '', props = {};
    switch (type) {
      case 'actor':
        w = 60; h = 100; name = 'Acteur';
        break;
      case 'usecase':
        w = 160; h = 80; name = 'Cas d\'utilisation';
        break;
      case 'class':
        w = 180; h = 120; name = 'Classe';
        props = { stereotype: '', attributes: [{ name: 'attribut', type: 'String', visibility: '+' }], methods: [{ name: 'methode', returnType: 'void', params: '', visibility: '+' }] };
        break;
      case 'action':
        w = 150; h = 60; name = 'Action';
        break;
      case 'decision':
        w = 80; h = 80; name = 'Condition';
        break;
      case 'start':
        w = 40; h = 40; name = 'Début';
        break;
      case 'end':
        w = 40; h = 40; name = 'Fin';
        break;
      case 'merge':
        w = 80; h = 80; name = 'Fusion';
        break;
    }
    x -= w / 2;
    y -= h / 2;
    const id = createElementDB(this.diagramId, type, name, x, y, w, h, props);
    this.loadDiagram(this.diagramId);
    const el = this.elements.find(e => e.id === id);
    if (el) {
      this.selected = el;
      showProperties(el);
    }
    this.currentTool = 'select';
    updateToolButtons();
    this.canvas.style.cursor = 'default';
    this.draw();
  }

  deleteSelected() {
    if (this.selected) {
      deleteElementDB(this.selected.id);
      this.selected = null;
      showEmptyProperties();
      this.loadDiagram(this.diagramId);
      this.draw();
    } else if (this.selectedConnection) {
      deleteConnectionDB(this.selectedConnection.id);
      this.selectedConnection = null;
      showEmptyProperties();
      this.loadDiagram(this.diagramId);
      this.draw();
    }
  }

  duplicateSelected() {
    if (!this.selected) return;
    const el = this.selected;
    const newId = createElementDB(this.diagramId, el.type, el.name + ' (copie)', el.x + 30, el.y + 30, el.width, el.height, JSON.parse(JSON.stringify(el.properties)));
    this.loadDiagram(this.diagramId);
    const newEl = this.elements.find(e => e.id === newId);
    if (newEl) {
      this.selected = newEl;
      showProperties(newEl);
    }
    this.draw();
  }

  loadDiagram(diagramId) {
    this.diagramId = diagramId;
    const diagram = getDiagram(diagramId);
    if (diagram) this.diagramType = diagram.type;
    this.elements = getElements(diagramId);
    this.connections = getConnections(diagramId);
    this.selected = null;
    this.selectedConnection = null;
    this.draw();
  }

  clear() {
    this.elements = [];
    this.connections = [];
    this.selected = null;
    this.selectedConnection = null;
    this.diagramId = null;
    this.draw();
  }

  zoomIn() {
    this.zoom = Math.min(3, this.zoom + 0.15);
    document.getElementById('zoomLevel').textContent = Math.round(this.zoom * 100) + '%';
    this.draw();
  }

  zoomOut() {
    this.zoom = Math.max(0.2, this.zoom - 0.15);
    document.getElementById('zoomLevel').textContent = Math.round(this.zoom * 100) + '%';
    this.draw();
  }

  zoomReset() {
    this.zoom = 1;
    this.panX = 0;
    this.panY = 0;
    document.getElementById('zoomLevel').textContent = '100%';
    this.draw();
  }

  draw() {
    const ctx = this.ctx;
    const w = this.canvas.width;
    const h = this.canvas.height;
    ctx.clearRect(0, 0, w, h);

    if (!this.diagramId) return;

    ctx.save();
    ctx.translate(this.panX, this.panY);
    ctx.scale(this.zoom, this.zoom);

    for (const conn of this.connections) {
      this.drawConnection(conn);
    }

    for (const el of this.elements) {
      this.drawElement(el);
    }

    ctx.restore();
  }

  drawElement(el) {
    const ctx = this.ctx;
    const isSelected = this.selected && this.selected.id === el.id;
    const isHovered = this.hovered && this.hovered.id === el.id;

    ctx.save();

    switch (el.type) {
      case 'actor': this.drawActor(el, ctx, isSelected, isHovered); break;
      case 'usecase': this.drawUseCase(el, ctx, isSelected, isHovered); break;
      case 'class': this.drawClass(el, ctx, isSelected, isHovered); break;
      case 'action': this.drawAction(el, ctx, isSelected, isHovered); break;
      case 'decision': this.drawDecision(el, ctx, isSelected, isHovered); break;
      case 'start': this.drawStart(el, ctx, isSelected, isHovered); break;
      case 'end': this.drawEnd(el, ctx, isSelected, isHovered); break;
      default: this.drawDefault(el, ctx, isSelected, isHovered); break;
    }

    ctx.restore();
  }

  drawActor(el, ctx, sel, hov) {
    const cx = el.x + el.width / 2;
    const cy = el.y + el.height / 2;
    const r = 12;
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#e0e0f0';

    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.fillStyle = 'transparent';

    ctx.beginPath();
    ctx.arc(cx, el.y + r + 4, r, 0, Math.PI * 2);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(cx, el.y + r * 2 + 4);
    ctx.lineTo(cx, cy + 8);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(cx - 18, cy - 4);
    ctx.lineTo(cx + 18, cy - 4);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(cx, cy + 8);
    ctx.lineTo(cx - 14, el.y + el.height);
    ctx.moveTo(cx, cy + 8);
    ctx.lineTo(cx + 14, el.y + el.height);
    ctx.stroke();

    this.drawText(ctx, el.name, cx, el.y + el.height + 16, color, 11, 'center');

    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawUseCase(el, ctx, sel, hov) {
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#e0e0f0';
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.fillStyle = 'rgba(124, 92, 252, 0.05)';

    ctx.beginPath();
    ctx.ellipse(el.x + el.width / 2, el.y + el.height / 2, el.width / 2, el.height / 2, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();

    this.drawText(ctx, el.name, el.x + el.width / 2, el.y + el.height / 2, color, 12, 'center');
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawClass(el, ctx, sel, hov) {
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#e0e0f0';
    const props = el.properties || {};
    const attrs = props.attributes || [];
    const methods = props.methods || [];
    const stereotype = props.stereotype || '';

    const attrH = Math.max(30, attrs.length * 18 + 8);
    const methH = Math.max(30, methods.length * 18 + 8);
    el.height = 30 + attrH + methH;
    if (stereotype) el.height += 16;

    ctx.strokeStyle = color;
    ctx.lineWidth = 1.5;
    ctx.fillStyle = 'rgba(124, 92, 252, 0.08)';
    ctx.fillRect(el.x, el.y, el.width, el.height);
    ctx.strokeRect(el.x, el.y, el.width, el.height);

    let yOffset = el.y;

    if (stereotype) {
      this.drawText(ctx, `«${stereotype}»`, el.x + el.width / 2, yOffset + 12, '#8888aa', 10, 'center');
      yOffset += 16;
    }

    ctx.fillStyle = 'rgba(124, 92, 252, 0.12)';
    ctx.fillRect(el.x, yOffset, el.width, 26);
    ctx.beginPath();
    ctx.moveTo(el.x, yOffset + 26);
    ctx.lineTo(el.x + el.width, yOffset + 26);
    ctx.stroke();
    this.drawText(ctx, el.name, el.x + el.width / 2, yOffset + 17, color, 13, 'center', true);
    yOffset += 26;

    ctx.beginPath();
    ctx.moveTo(el.x, yOffset + attrH);
    ctx.lineTo(el.x + el.width, yOffset + attrH);
    ctx.stroke();

    ctx.save();
    ctx.beginPath();
    ctx.rect(el.x, yOffset, el.width, attrH);
    ctx.clip();
    attrs.forEach((a, i) => {
      const text = `${a.visibility || '+'} ${a.name}: ${a.type}`;
      this.drawText(ctx, text, el.x + 8, yOffset + 16 + i * 18, '#c0c0d0', 11, 'left');
    });
    ctx.restore();

    yOffset += attrH;

    ctx.save();
    ctx.beginPath();
    ctx.rect(el.x, yOffset, el.width, methH);
    ctx.clip();
    methods.forEach((m, i) => {
      const text = `${m.visibility || '+'} ${m.name}(${m.params || ''}): ${m.returnType || 'void'}`;
      this.drawText(ctx, text, el.x + 8, yOffset + 16 + i * 18, '#c0c0d0', 11, 'left');
    });
    ctx.restore();

    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawAction(el, ctx, sel, hov) {
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#e0e0f0';
    const r = 12;
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.5;
    ctx.fillStyle = 'rgba(96, 165, 250, 0.08)';

    ctx.beginPath();
    ctx.roundRect(el.x, el.y, el.width, el.height, r);
    ctx.fill();
    ctx.stroke();

    this.drawText(ctx, el.name, el.x + el.width / 2, el.y + el.height / 2, color, 12, 'center');
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawDecision(el, ctx, sel, hov) {
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#fbbf24';
    const cx = el.x + el.width / 2;
    const cy = el.y + el.height / 2;
    const hw = el.width / 2;
    const hh = el.height / 2;

    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.fillStyle = 'rgba(251, 191, 36, 0.08)';

    ctx.beginPath();
    ctx.moveTo(cx, el.y);
    ctx.lineTo(el.x + el.width, cy);
    ctx.lineTo(cx, el.y + el.height);
    ctx.lineTo(el.x, cy);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    this.drawText(ctx, el.name, cx, cy + 4, color, 10, 'center');
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawStart(el, ctx, sel) {
    const cx = el.x + el.width / 2;
    const cy = el.y + el.height / 2;
    const r = el.width / 2;
    ctx.fillStyle = sel ? '#7c5cfc' : '#4ade80';
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fill();
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawEnd(el, ctx, sel) {
    const cx = el.x + el.width / 2;
    const cy = el.y + el.height / 2;
    const r = el.width / 2;
    ctx.strokeStyle = sel ? '#7c5cfc' : '#f87171';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(cx, cy, r - 5, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = ctx.strokeStyle;
    ctx.beginPath();
    ctx.arc(cx, cy, r - 5, 0, Math.PI * 2);
    ctx.fill();
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawDefault(el, ctx, sel, hov) {
    const color = sel ? '#7c5cfc' : hov ? '#9b7eff' : '#e0e0f0';
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.5;
    ctx.fillStyle = 'rgba(124, 92, 252, 0.05)';
    ctx.fillRect(el.x, el.y, el.width, el.height);
    ctx.strokeRect(el.x, el.y, el.width, el.height);
    this.drawText(ctx, el.name, el.x + el.width / 2, el.y + el.height / 2, color, 12, 'center');
    if (sel) this.drawSelectionBox(el, ctx);
  }

  drawSelectionBox(el, ctx) {
    ctx.strokeStyle = '#7c5cfc';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    ctx.strokeRect(el.x - 4, el.y - 4, el.width + 8, el.height + 8);
    ctx.setLineDash([]);

    const handles = [
      { x: el.x - 4, y: el.y - 4 },
      { x: el.x + el.width - 2, y: el.y - 4 },
      { x: el.x - 4, y: el.y + el.height - 2 },
      { x: el.x + el.width - 2, y: el.y + el.height - 2 }
    ];
    handles.forEach(h => {
      ctx.fillStyle = '#7c5cfc';
      ctx.fillRect(h.x, h.y, 6, 6);
    });
  }

  drawConnection(conn) {
    const src = this.elements.find(e => e.id === conn.source_id);
    const tgt = this.elements.find(e => e.id === conn.target_id);
    if (!src || !tgt) return;

    const ctx = this.ctx;
    const sx = src.x + src.width / 2;
    const sy = src.y + src.height / 2;
    const tx = tgt.x + tgt.width / 2;
    const ty = tgt.y + tgt.height / 2;

    const isSel = this.selectedConnection && this.selectedConnection.id === conn.id;
    ctx.strokeStyle = isSel ? '#7c5cfc' : '#8888aa';
    ctx.fillStyle = isSel ? '#7c5cfc' : '#8888aa';
    ctx.lineWidth = isSel ? 2.5 : 1.5;

    const angle = Math.atan2(ty - sy, tx - sx);
    const s = this.getConnectionPoint(src, angle + Math.PI);
    const t = this.getConnectionPoint(tgt, angle);

    switch (conn.type) {
      case 'inheritance':
        this.drawInheritance(s, t, angle, ctx); break;
      case 'aggregation':
        this.drawAggregation(s, t, angle, ctx, false); break;
      case 'composition':
        this.drawAggregation(s, t, angle, ctx, true); break;
      case 'include':
        this.drawDashedLine(s, t, ctx); this.drawArrow(t, angle, ctx); break;
      case 'extend':
        this.drawDashedLine(s, t, ctx); this.drawArrow(t, angle, ctx); break;
      case 'transition':
        this.drawSolidLine(s, t, ctx); this.drawArrow(t, angle, ctx); break;
      default:
        this.drawSolidLine(s, t, ctx); this.drawArrow(t, angle, ctx);
    }

    if (conn.label) {
      const mx = (s.x + t.x) / 2;
      const my = (s.y + t.y) / 2;
      this.drawText(ctx, conn.label, mx, my - 8, isSel ? '#7c5cfc' : '#aaa', 10, 'center');
    }

    if (conn.properties?.multiplicity) {
      const ms = conn.properties.multiplicity;
      if (ms.source) {
        const la = angle + Math.PI;
        this.drawText(ctx, ms.source, sx + Math.cos(la) * 20, sy + Math.sin(la) * 20, '#fbbf24', 10, 'center');
      }
      if (ms.target) {
        this.drawText(ctx, ms.target, tx + Math.cos(angle) * 20, ty + Math.sin(angle) * 20, '#fbbf24', 10, 'center');
      }
    }
  }

  getConnectionPoint(el, angle) {
    const cx = el.x + el.width / 2;
    const cy = el.y + el.height / 2;
    const hw = el.width / 2 + 4;
    const hh = el.height / 2 + 4;
    const dx = Math.cos(angle);
    const dy = Math.sin(angle);
    if (Math.abs(dx * hh) > Math.abs(dy * hw)) {
      return { x: cx + (dx > 0 ? hw : -hw), y: cy + dy * hw / Math.abs(dx) };
    }
    return { x: cx + dx * hh / Math.abs(dy), y: cy + (dy > 0 ? hh : -hh) };
  }

  drawSolidLine(s, t, ctx) {
    ctx.beginPath();
    ctx.moveTo(s.x, s.y);
    ctx.lineTo(t.x, t.y);
    ctx.stroke();
  }

  drawDashedLine(s, t, ctx) {
    ctx.setLineDash([6, 4]);
    ctx.beginPath();
    ctx.moveTo(s.x, s.y);
    ctx.lineTo(t.x, t.y);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  drawArrow(t, angle, ctx) {
    const size = 10;
    ctx.beginPath();
    ctx.moveTo(t.x, t.y);
    ctx.lineTo(t.x + Math.cos(angle + 2.7) * size, t.y + Math.sin(angle + 2.7) * size);
    ctx.lineTo(t.x + Math.cos(angle - 2.7) * size, t.y + Math.sin(angle - 2.7) * size);
    ctx.closePath();
    ctx.fill();
  }

  drawInheritance(s, t, angle, ctx) {
    this.drawSolidLine(s, t, ctx);
    const size = 12;
    ctx.fillStyle = 'transparent';
    ctx.strokeStyle = ctx.strokeStyle;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(t.x, t.y);
    ctx.lineTo(t.x + Math.cos(angle + 2.7) * size, t.y + Math.sin(angle + 2.7) * size);
    ctx.lineTo(t.x + Math.cos(angle - 2.7) * size, t.y + Math.sin(angle - 2.7) * size);
    ctx.closePath();
    ctx.stroke();
  }

  drawAggregation(s, t, angle, ctx, filled) {
    this.drawSolidLine(s, t, ctx);
    const size = 10;
    ctx.fillStyle = filled ? ctx.strokeStyle : 'transparent';
    ctx.beginPath();
    ctx.moveTo(s.x, s.y);
    ctx.lineTo(s.x + Math.cos(angle + 2.7) * size, s.y + Math.sin(angle + 2.7) * size);
    ctx.lineTo(s.x + Math.cos(angle) * size * 1.5, s.y + Math.sin(angle) * size * 1.5);
    ctx.lineTo(s.x + Math.cos(angle - 2.7) * size, s.y + Math.sin(angle - 2.7) * size);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
  }

  drawText(ctx, text, x, y, color, size, align = 'left', bold = false) {
    ctx.fillStyle = color;
    ctx.font = `${bold ? 'bold ' : ''}${size}px 'Segoe UI', system-ui, sans-serif`;
    ctx.textAlign = align;
    ctx.textBaseline = 'middle';
    const maxWidth = (this.selected ? this.selected.width || 180 : 180);
    if (ctx.measureText(text).width > maxWidth) {
      while (ctx.measureText(text + '…').width > maxWidth && text.length > 0) {
        text = text.slice(0, -1);
      }
      text += '…';
    }
    ctx.fillText(text, x, y);
  }
}
