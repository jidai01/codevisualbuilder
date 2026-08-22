'use client';

import { useCallback, useState, useEffect, useRef } from 'react';
import { useParams, useRouter } from 'next/navigation';
import {
  ReactFlow,
  Controls,
  Background,
  BackgroundVariant,
  ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { TableNode } from '@/components/TableNode';
import FileExplorer from '@/components/FileExplorer';
import CodeEditor from '@/components/CodeEditor';
import Walkthrough, { TourTrigger } from '@/components/Walkthrough';
import { useCanvasStore } from '@/lib/store';

const nodeTypes = { tableNode: TableNode };

type TabId = 'canvas' | 'code' | 'preview';

function Canvas() {
  const {
    nodes, edges, onNodesChange, onEdgesChange, onConnect, addNode,
    projectName, setProjectName, generateBlueprint, hydrateFromBlueprint,
    view, setView, workspaceUuid, setWorkspaceUuid,
  } = useCanvasStore();

  const params = useParams();
  const router = useRouter();
  const uuidParam = params?.uuid as string;

  const [activeTab, setActiveTab] = useState<TabId>('canvas');
  const [generating, setGenerating] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hydrating, setHydrating] = useState(true);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [activeFile, setActiveFile] = useState<string | null>(null);
  const [consoleLogs, setConsoleLogs] = useState<string[]>([]);
  const [showConsole, setShowConsole] = useState(false);
  const consoleRef = useRef<HTMLDivElement>(null);
  const [sidebarWidth, setSidebarWidth] = useState(256);
  const isResizing = useRef(false);

  useEffect(() => {
    if (uuidParam === 'new') {
      useCanvasStore.getState().resetCanvas();
      setHydrating(false);
      return;
    }
    if (uuidParam && uuidParam !== 'new') {
      if (workspaceUuid === uuidParam && nodes.length > 0) {
        setHydrating(false);
        return;
      }
      setWorkspaceUuid(uuidParam);
      fetchBlueprint(uuidParam);
    }
  }, [uuidParam]);

  const fetchBlueprint = async (uuid: string) => {
    try {
      const res = await fetch(`http://localhost:8000/api/workspaces/${uuid}/blueprint`);
      if (!res.ok) { setError('Project not found'); setHydrating(false); return; }
      const blueprint = await res.json();
      hydrateFromBlueprint(uuid, blueprint);
    } catch { setError('Failed to load project'); }
    finally { setHydrating(false); }
  };

  const onDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
  }, []);

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('application/reactflow');
    if (!type) return;
    addNode({ x: e.clientX - 300, y: e.clientY - 50 });
  }, [addNode]);

  const handleGenerate = async () => {
    if (nodes.length === 0) { setError('Add at least one table'); return; }
    setGenerating(true); setError(null);
    try {
      const blueprint = generateBlueprint();
      const res = await fetch('http://localhost:8000/api/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(blueprint),
      });
      const data = await res.json();
      if (data.success) {
        setWorkspaceUuid(data.uuid);
        router.replace(`/builder/${data.uuid}`);
        setView('ide');
        setActiveTab('code');
      } else {
        setError(data.error || JSON.stringify(data.errors) || 'Generation failed');
      }
    } catch { setError('Failed to connect to backend.'); }
    finally { setGenerating(false); }
  };

  const handleSync = async () => {
    if (!workspaceUuid || nodes.length === 0) return;
    setSyncing(true); setError(null);
    try {
      const blueprint = generateBlueprint();
      const res = await fetch(`http://localhost:8000/api/workspace/${workspaceUuid}/sync`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(blueprint),
      });
      const data = await res.json();
      if (!data.success) setError(data.error || 'Sync failed');
    } catch { setError('Sync failed'); }
    finally { setSyncing(false); }
  };

  const handleStartPreview = async () => {
    if (!workspaceUuid) return;
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${workspaceUuid}/serve`, { method: 'POST' });
      const data = await res.json();
      if (data.url) { setPreviewUrl(data.url); setActiveTab('preview'); }
      else setError(data.error || 'Failed to start preview');
    } catch { setError('Failed to start preview server'); }
  };

  const fetchLogs = async () => {
    if (!workspaceUuid) return;
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${workspaceUuid}/logs`);
      const data = await res.json();
      setConsoleLogs(data.lines || []);
    } catch {}
  };

  useEffect(() => {
    if (activeTab === 'preview' && workspaceUuid) {
      fetchLogs();
      const interval = setInterval(fetchLogs, 5000);
      return () => clearInterval(interval);
    }
  }, [activeTab, workspaceUuid]);

  useEffect(() => {
    if (consoleRef.current) consoleRef.current.scrollTop = consoleRef.current.scrollHeight;
  }, [consoleLogs]);

  const handleBackToDashboard = () => {
    useCanvasStore.getState().resetCanvas();
    router.push('/');
  };

  const handleResizeStart = (e: React.MouseEvent) => {
    e.preventDefault();
    isResizing.current = true;
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    const startX = e.clientX;
    const startWidth = sidebarWidth;

    const onMouseMove = (ev: MouseEvent) => {
      if (!isResizing.current) return;
      const newWidth = Math.min(Math.max(startWidth + ev.clientX - startX, 160), 500);
      setSidebarWidth(newWidth);
    };

    const onMouseUp = () => {
      isResizing.current = false;
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
    };

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  };

  if (hydrating) {
    return (
      <div className="loading-screen">
        <div className="loading-spinner" />
        <span>Loading project...</span>
      </div>
    );
  }

  const isGenerated = !!workspaceUuid && uuidParam !== 'new';

  return (
    <div className="workspace-root">
      <Walkthrough />

      <header className="workspace-header">
        <div className="workspace-header-left">
          <button onClick={handleBackToDashboard} className="btn btn-ghost">← Projects</button>
          <span className="workspace-logo">CodeVisualBuilder</span>
          <span className="workspace-sep">/</span>
          <input
            type="text" value={projectName}
            onChange={(e) => setProjectName(e.target.value)}
            className="workspace-name-input"
          />
        </div>

        <div className="workspace-tabs">
          <button className={`workspace-tab ${activeTab === 'canvas' ? 'active' : ''}`} onClick={() => setActiveTab('canvas')}>
            <span className="workspace-tab-icon">◇</span> Canvas
          </button>
          <button
            className={`workspace-tab ${activeTab === 'code' ? 'active' : ''}`}
            onClick={() => { if (isGenerated) setActiveTab('code'); }}
            disabled={!isGenerated}
          >
            <span className="workspace-tab-icon">&lt;/&gt;</span> Code
          </button>
          <button
            className={`workspace-tab ${activeTab === 'preview' ? 'active' : ''}`}
            onClick={() => { if (isGenerated) { if (!previewUrl) handleStartPreview(); else setActiveTab('preview'); }}}
            disabled={!isGenerated}
          >
            <span className="workspace-tab-icon">▶</span> Preview
          </button>
        </div>

        <div className="workspace-header-right">
          {error && <span className="workspace-error">{error}</span>}
          {activeTab === 'canvas' && (
            <>
              <button className="btn btn-green" onClick={() => addNode({ x: 400, y: 200 })}>+ Table</button>
              {isGenerated ? (
                <button className="btn btn-yellow" onClick={handleSync} disabled={syncing}>
                  {syncing ? 'Syncing...' : '⟳ Sync Changes'}
                </button>
              ) : (
                <button className="btn btn-blue" onClick={handleGenerate} disabled={generating} data-tour="generate-button">
                  {generating ? 'Generating...' : 'Generate & Edit'}
                </button>
              )}
            </>
          )}
          {isGenerated && (
            <span className="workspace-uuid">{workspaceUuid.slice(0, 8)}...</span>
          )}
        </div>
      </header>

      <div className="workspace-body">
        {activeTab === 'canvas' && (
          <div className="workspace-canvas-layout">
            <aside className="sidebar" data-tour="node-palette">
              <h2>Drag &amp; Drop</h2>
              <div className="drag-item" draggable onDragStart={(e) => { e.dataTransfer.setData('application/reactflow', 'table'); e.dataTransfer.effectAllowed = 'move'; }}>
                Table Node
              </div>
              <p>Drag onto canvas to create entities. Connect by dragging between handles.</p>
            </aside>
            <div className="canvas-container" data-tour="canvas-area">
              <ReactFlow
                nodes={nodes} edges={edges}
                onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} onConnect={onConnect}
                onDrop={onDrop} onDragOver={onDragOver} nodeTypes={nodeTypes} fitView
                connectionLineStyle={{ stroke: '#3b82f6', strokeWidth: 2 }}
                defaultEdgeOptions={{ style: { stroke: '#3b82f6', strokeWidth: 2 } }}
              >
                <Controls />
                <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
              </ReactFlow>
            </div>
          </div>
        )}

        {activeTab === 'code' && isGenerated && (
          <div className="workspace-code-layout">
            <div className="workspace-sidebar" style={{ width: sidebarWidth, flexShrink: 0 }}>
              <FileExplorer uuid={workspaceUuid!} onFileSelect={setActiveFile} activeFile={activeFile} />
            </div>
            <div className="resize-handle" onMouseDown={handleResizeStart} />
            <div className="workspace-editor">
              <CodeEditor uuid={workspaceUuid!} filePath={activeFile} />
            </div>
          </div>
        )}

        {activeTab === 'preview' && isGenerated && (
          <div className="workspace-preview-layout">
            <div className="workspace-preview-top">
              <div className="workspace-preview-bar">
                <span className="workspace-preview-url">{previewUrl || 'Not started'}</span>
                <div className="workspace-preview-actions">
                  <button className="btn btn-sm" onClick={handleStartPreview}>⟳ Restart</button>
                  <button className="btn btn-sm" onClick={() => setActiveTab('code')}>Open Logs</button>
                </div>
              </div>
              {previewUrl ? (
                <iframe src={previewUrl} className="workspace-preview-iframe" />
              ) : (
                <div className="workspace-preview-empty">
                  <button className="btn btn-blue" onClick={handleStartPreview}>Start Preview Server</button>
                </div>
              )}
            </div>
            <div className={`workspace-console ${showConsole ? 'expanded' : ''}`}>
              <div className="workspace-console-header" onClick={() => setShowConsole(!showConsole)}>
                <span>Console Output</span>
                <span>{showConsole ? '▼' : '▶'}</span>
              </div>
              {showConsole && (
                <div className="workspace-console-output" ref={consoleRef}>
                  {consoleLogs.length === 0 ? (
                    <div className="workspace-console-empty">No logs yet.</div>
                  ) : (
                    consoleLogs.map((line, i) => <div key={i} className="workspace-console-line">{line}</div>)
                  )}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      <TourTrigger />
    </div>
  );
}

export default function BuilderPage() {
  return (
    <ReactFlowProvider>
      <Canvas />
    </ReactFlowProvider>
  );
}
