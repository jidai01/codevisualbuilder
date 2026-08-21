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
    <div className="h-screen flex flex-col bg-[#1e1e1e]">
      <header className="flex items-center justify-between px-4 py-2 bg-[#323233] border-b border-[#333]">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="text-gray-400 hover:text-white text-sm flex items-center gap-1"
          >
            ← Back
          </button>
          <div className="flex items-center gap-2">
            <span className="text-blue-400 font-bold">CodeVisualBuilder</span>
            <span className="text-gray-600">/</span>
            <span className="text-white font-medium">{projectName}</span>
          </div>
        </div>
        <div className="flex items-center gap-4">
          {savedMessage && (
            <span className="text-green-400 text-sm animate-pulse">{savedMessage}</span>
          )}
          <button
            onClick={() => setShowExport(!showExport)}
            className={`px-3 py-1.5 text-sm rounded font-medium transition-colors ${
              showExport
                ? 'bg-purple-600 text-white'
                : 'bg-[#333] text-gray-400 hover:text-white hover:bg-[#444]'
            }`}
          >
            Export
          </button>
          <span className="text-xs text-gray-600">UUID: {uuid.slice(0, 8)}...</span>
        </div>
      </header>

      {openFiles.length > 0 && (
        <div className="flex bg-[#252526] border-b border-[#333] overflow-x-auto">
          {openFiles.map((path) => {
            const name = path.split('/').pop() || path;
            const isActive = activeFile === path;

            return (
              <div
                key={path}
                onClick={() => setActiveFile(path)}
                className={`flex items-center gap-2 px-3 py-2 text-sm cursor-pointer border-r border-[#333] min-w-0 ${
                  isActive
                    ? 'bg-[#1e1e1e] text-white'
                    : 'bg-[#2d2d2d] text-gray-500 hover:text-gray-300'
                }`}
              >
                <span className="truncate max-w-[120px]">{name}</span>
                <button
                  onClick={(e) => closeTab(path, e)}
                  className="text-gray-600 hover:text-white text-xs ml-1"
                >
                  ×
                </button>
              </div>
            );
          })}
        </div>
      )}

      <div className="flex flex-1 min-h-0">
        <div className="w-64 border-r border-[#333] flex-shrink-0">
          <FileExplorer
            uuid={uuid}
            onFileSelect={handleFileSelect}
            activeFile={activeFile}
          />
        </div>

        <div className="flex-1 min-w-0">
          <CodeEditor
            uuid={uuid}
            filePath={activeFile}
            onFileSaved={handleFileSaved}
          />
        </div>

        {showExport && (
          <div className="w-72 border-l border-[#333] p-4 overflow-y-auto bg-[#252526]">
            <ExportPanel uuid={uuid} projectName={projectName} />
          </div>
        )}
      </div>
    </div>
  );
}
