'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Swal from 'sweetalert2';

interface Workspace {
  uuid: string;
  project_name: string;
  entities_count: number;
  last_updated: number;
}

export default function Dashboard() {
  const router = useRouter();
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [loading, setLoading] = useState(true);
  const [editingUuid, setEditingUuid] = useState<string | null>(null);
  const [editValue, setEditValue] = useState('');
  const [saving, setSaving] = useState(false);
  const editInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    fetchWorkspaces();
  }, []);

  useEffect(() => {
    if (editingUuid && editInputRef.current) {
      editInputRef.current.focus();
      editInputRef.current.select();
    }
  }, [editingUuid]);

  const fetchWorkspaces = async () => {
    try {
      const res = await fetch('/api/workspaces');
      const data = await res.json();
      setWorkspaces(data);
    } catch (err) {
      console.error('Failed to fetch workspaces:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleRenameStart = (e: React.MouseEvent, uuid: string, currentName: string) => {
    e.stopPropagation();
    setEditingUuid(uuid);
    setEditValue(currentName);
  };

  const handleRenameCancel = () => {
    setEditingUuid(null);
    setEditValue('');
  };

  const handleRenameSave = async (uuid: string) => {
    const trimmed = editValue.trim();
    if (!trimmed || trimmed === workspaces.find((w) => w.uuid === uuid)?.project_name) {
      handleRenameCancel();
      return;
    }

    setSaving(true);
    try {
      const res = await fetch(`/api/workspaces/${uuid}/rename`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: trimmed }),
      });
      const data = await res.json();
      if (data.success) {
        setWorkspaces((prev) =>
          prev.map((w) => (w.uuid === uuid ? { ...w, project_name: trimmed } : w))
        );
      }
    } catch (err) {
      console.error('Rename failed:', err);
    } finally {
      setSaving(false);
      setEditingUuid(null);
      setEditValue('');
    }
  };

  const handleRenameKeyDown = (e: React.KeyboardEvent, uuid: string) => {
    if (e.key === 'Enter') handleRenameSave(uuid);
    if (e.key === 'Escape') handleRenameCancel();
  };

  const handleDeleteProject = async (e: React.MouseEvent, uuid: string, name: string) => {
    e.stopPropagation();
    const result = await Swal.fire({
      title: 'Delete project?',
      html: `Are you sure you want to delete <strong>${name}</strong>?<br><span style="color:#94a3b8;font-size:13px">This cannot be undone.</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#475569',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel',
      background: '#1e293b',
      color: '#e2e8f0',
      customClass: { popup: 'swal-dark' },
    });

    if (!result.isConfirmed) return;

    try {
      const res = await fetch(`/api/workspaces/${uuid}`, { method: 'DELETE' });
      if (res.ok) {
        setWorkspaces((prev) => prev.filter((w) => w.uuid !== uuid));
        Swal.fire({
          title: 'Deleted',
          text: `${name} has been deleted.`,
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
          background: '#1e293b',
          color: '#e2e8f0',
        });
      }
    } catch (err) {
      Swal.fire({ title: 'Error', text: 'Failed to delete project.', icon: 'error', background: '#1e293b', color: '#e2e8f0' });
    }
  };

  const handleCreateNew = () => {
    router.push('/builder/new');
  };

  const handleOpenProject = (uuid: string) => {
    router.push(`/builder/${uuid}`);
  };

  const formatDate = (timestamp: number) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="dashboard">
      <header className="dashboard-header">
        <div className="dashboard-header-left">
          <h1 className="dashboard-logo">Code Visual Builder</h1>
          <span className="dashboard-subtitle">Laravel Filament Project Generator</span>
        </div>
        <button className="dashboard-create-btn" onClick={handleCreateNew}>
          + New Project
        </button>
      </header>

      <main className="dashboard-main">
        <div className="dashboard-section-header">
          <h2>Recent Projects</h2>
          <span className="dashboard-count">{workspaces.length} project{workspaces.length !== 1 ? 's' : ''}</span>
        </div>

        {loading ? (
          <div className="dashboard-loading">Loading projects...</div>
        ) : workspaces.length === 0 ? (
          <div className="dashboard-empty">
            <div className="dashboard-empty-icon">📦</div>
            <h3>No projects yet</h3>
            <p>Create your first Laravel Filament project by clicking the button above.</p>
            <button className="dashboard-create-btn large" onClick={handleCreateNew}>
              + Create First Project
            </button>
          </div>
        ) : (
          <div className="dashboard-grid">
            {workspaces.map((ws) => {
              const isEditing = editingUuid === ws.uuid;

              return (
                <div
                  key={ws.uuid}
                  className="dashboard-card"
                  onClick={() => !isEditing && handleOpenProject(ws.uuid)}
                >
                  <div className="dashboard-card-header">
                    <div className="dashboard-card-icon">📁</div>
                    <span className="dashboard-card-uuid">{ws.uuid.slice(0, 8)}...</span>
                  </div>

                  {isEditing ? (
                    <div className="dashboard-card-edit" onClick={(e) => e.stopPropagation()}>
                      <input
                        ref={editInputRef}
                        type="text"
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onKeyDown={(e) => handleRenameKeyDown(e, ws.uuid)}
                        onBlur={() => handleRenameSave(ws.uuid)}
                        className="dashboard-card-edit-input"
                        disabled={saving}
                        maxLength={255}
                      />
                      <div className="dashboard-card-edit-actions">
                        <button
                          className="dashboard-card-edit-save"
                          onClick={() => handleRenameSave(ws.uuid)}
                          disabled={saving}
                        >
                          {saving ? '...' : '✓'}
                        </button>
                        <button
                          className="dashboard-card-edit-cancel"
                          onClick={handleRenameCancel}
                          disabled={saving}
                        >
                          ✕
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="dashboard-card-name-row">
                      <h3 className="dashboard-card-name">{ws.project_name}</h3>
                      <button
                        className="dashboard-card-rename"
                        onClick={(e) => handleRenameStart(e, ws.uuid, ws.project_name)}
                        title="Rename"
                      >
                        ✎
                      </button>
                    </div>
                  )}

                  <div className="dashboard-card-meta">
                    <span>{ws.entities_count} entit{ws.entities_count !== 1 ? 'ies' : 'y'}</span>
                    <span className="dashboard-card-dot">·</span>
                    <span>{formatDate(ws.last_updated)}</span>
                  </div>
                  <div className="dashboard-card-footer">
                    <button className="dashboard-card-delete" onClick={(e) => handleDeleteProject(e, ws.uuid, ws.project_name)}>
                      Delete
                    </button>
                    <span className="dashboard-card-action">Open →</span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>
    </div>
  );
}
