'use client';

import { useState, useCallback } from 'react';
import FileExplorer from '@/components/FileExplorer';
import CodeEditor from '@/components/CodeEditor';
import ExportPanel from '@/components/ExportPanel';

interface IDEPageProps {
  uuid: string;
  projectName: string;
  onBack: () => void;
}

export default function IDEPage({ uuid, projectName, onBack }: IDEPageProps) {
  const [activeFile, setActiveFile] = useState<string | null>(null);
  const [openFiles, setOpenFiles] = useState<string[]>([]);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);
  const [showExport, setShowExport] = useState(false);

  const handleFileSelect = useCallback((path: string) => {
    setActiveFile(path);
    setOpenFiles((prev) => {
      if (prev.includes(path)) return prev;
      return [...prev, path];
    });
  }, []);

  const handleFileSaved = useCallback((path: string) => {
    setSavedMessage(`Saved: ${path}`);
    setTimeout(() => setSavedMessage(null), 2000);
  }, []);

  const closeTab = useCallback((path: string, e: React.MouseEvent) => {
    e.stopPropagation();
    setOpenFiles((prev) => prev.filter((f) => f !== path));
    if (activeFile === path) {
      const remaining = openFiles.filter((f) => f !== path);
      setActiveFile(remaining.length > 0 ? remaining[remaining.length - 1] : null);
    }
  }, [activeFile, openFiles]);

  return (
    <div className="ide-page">
      <header className="ide-header">
        <div className="ide-header-left">
          <button onClick={onBack} className="ide-back-btn">
            ← Back
          </button>
          <div className="ide-breadcrumb">
            <span className="ide-breadcrumb-project">CodeVisualBuilder</span>
            <span className="ide-breadcrumb-sep">/</span>
            <span className="ide-breadcrumb-name">{projectName}</span>
          </div>
        </div>
        <div className="ide-header-right">
          {savedMessage && (
            <span className="ide-saved-msg">{savedMessage}</span>
          )}
          <button
            onClick={() => setShowExport(!showExport)}
            data-tour="export-button"
            className={`ide-export-btn ${showExport ? 'active' : ''}`}
          >
            Export
          </button>
          <span className="ide-uuid">UUID: {uuid.slice(0, 8)}...</span>
        </div>
      </header>

      {openFiles.length > 0 && (
        <div className="ide-tabs">
          {openFiles.map((path) => {
            const name = path.split('/').pop() || path;
            const isActive = activeFile === path;

            return (
              <div
                key={path}
                onClick={() => setActiveFile(path)}
                className={`ide-tab ${isActive ? 'active' : ''}`}
              >
                <span className="ide-tab-name">{name}</span>
                <button
                  onClick={(e) => closeTab(path, e)}
                  className="ide-tab-close"
                >
                  ×
                </button>
              </div>
            );
          })}
        </div>
      )}

      <div className="ide-main">
        <div className="ide-sidebar">
          <FileExplorer
            uuid={uuid}
            onFileSelect={handleFileSelect}
            activeFile={activeFile}
          />
        </div>

        <div className="ide-editor-area">
          <CodeEditor
            uuid={uuid}
            filePath={activeFile}
            onFileSaved={handleFileSaved}
          />
        </div>

        {showExport && (
          <div className="ide-export-panel">
            <ExportPanel uuid={uuid} projectName={projectName} />
          </div>
        )}
      </div>
    </div>
  );
}
