'use client';

import { useCallback } from 'react';
import {
  ReactFlow,
  Controls,
  Background,
  BackgroundVariant,
  ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { TableNode } from '@/components/TableNode';
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
  } = useCanvasStore();

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

  const handleGenerate = () => {
    const blueprint = generateBlueprint();
    console.log('=== GLOBAL BLUEPRINT JSON ===');
    console.log(JSON.stringify(blueprint, null, 2));
    alert('Blueprint JSON logged to console! (F12)');
  };

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
          <button
            className="btn btn-green"
            onClick={() => addNode({ x: 400, y: 200 })}
          >
            + Add Table
          </button>
          <button className="btn btn-blue" onClick={handleGenerate}>
            Generate Blueprint
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

export default function CanvasPage() {
  return (
    <ReactFlowProvider>
      <Canvas />
    </ReactFlowProvider>
  );
}
