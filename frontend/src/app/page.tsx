'use client';

import { useCallback, useState } from 'react';
import {
  ReactFlow,
  Controls,
  Background,
  BackgroundVariant,
  ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { TableNode } from '@/components/TableNode';
import IDEPage from '@/components/IDEPage';
import { useCanvasStore } from '@/lib/store';

const nodeTypes = {
  tableNode: TableNode,
};

function Canvas() {
  const {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    addNode,
    projectName,
    setProjectName,
    generateBlueprint,
    view,
    setView,
    workspaceUuid,
    setWorkspaceUuid,
  } = useCanvasStore();

  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }, []);

  const onDrop = useCallback(
    (event: React.DragEvent) => {
      event.preventDefault();
      const type = event.dataTransfer.getData('application/reactflow');
      if (typeof type === 'undefined' || !type) return;

      const position = {
        x: event.clientX - 300,
        y: event.clientY - 50,
      };

      addNode(position);
    },
    [addNode]
  );

  const handleGenerate = async () => {
    if (nodes.length === 0) {
      setError('Add at least one table to generate');
      return;
    }

    setGenerating(true);
    setError(null);

    try {
      const blueprint = generateBlueprint();

      console.log('=== GLOBAL BLUEPRINT JSON ===');
      console.log(JSON.stringify(blueprint, null, 2));

      const res = await fetch('http://localhost:8000/api/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(blueprint),
      });

      const data = await res.json();

      if (data.success) {
        setWorkspaceUuid(data.uuid);
        setView('ide');
      } else {
        setError(data.error || data.errors ? JSON.stringify(data.errors) : 'Generation failed');
      }
    } catch (err) {
      setError('Failed to connect to backend. Make sure Laravel is running on port 8000.');
    } finally {
      setGenerating(false);
    }
  };

  if (view === 'ide' && workspaceUuid) {
    return (
      <IDEPage
        uuid={workspaceUuid}
        projectName={projectName}
        onBack={() => setView('canvas')}
      />
    );
  }

  return (
    <div className="app-container">
      <header className="header">
        <div className="header-controls">
          <h1>Code Visual Builder</h1>
          <label>Project:</label>
          <input
            type="text"
            value={projectName}
            onChange={(e) => setProjectName(e.target.value)}
          />
        </div>
        <div className="header-controls">
          {error && (
            <span className="text-red-400 text-sm mr-2">{error}</span>
          )}
          <button
            className="btn btn-green"
            onClick={() => addNode({ x: 400, y: 200 })}
          >
            + Add Table
          </button>
          <button
            className="btn btn-blue"
            onClick={handleGenerate}
            disabled={generating}
          >
            {generating ? 'Generating...' : 'Generate & Edit'}
          </button>
        </div>
      </header>

      <div className="main-content">
        <aside className="sidebar">
          <h2>Drag &amp; Drop</h2>
          <div
            className="drag-item"
            draggable
            onDragStart={(e) => {
              e.dataTransfer.setData('application/reactflow', 'table');
              e.dataTransfer.effectAllowed = 'move';
            }}
          >
            Table Node
          </div>
          <p>
            Drag a Table Node onto the canvas to create a new entity. Click the
            node header to rename. Add fields inside each node. Connect nodes by
            dragging from one handle to another.
          </p>
          <div style={{ marginTop: 'auto', padding: '12px 0', borderTop: '1px solid #475569' }}>
            <div style={{ fontSize: '11px', color: '#64748b', lineHeight: '1.6' }}>
              <strong style={{ color: '#94a3b8' }}>Quick Guide:</strong><br />
              1. Drag Table Node to canvas<br />
              2. Click header to rename<br />
              3. Add fields in each node<br />
              4. Connect nodes for relations<br />
              5. Click Generate &amp; Edit
            </div>
          </div>
        </aside>

        <div className="canvas-container">
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onDrop={onDrop}
            onDragOver={onDragOver}
            nodeTypes={nodeTypes}
            fitView
          >
            <Controls />
            <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
          </ReactFlow>
        </div>
      </div>
    </div>
  );
}

export default function Home() {
  return (
    <ReactFlowProvider>
      <Canvas />
    </ReactFlowProvider>
  );
}
