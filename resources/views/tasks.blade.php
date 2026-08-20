@extends('layouts.app')

@section('title', 'Mes tâches')

@section('content')
<h4 class="mb-3">Mes tâches de validation</h4>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>Étape</th><th>Workflow</th><th>Document</th><th>Échéance</th><th>Statut</th><th>Décision</th></tr>
        </thead>
        <tbody>
        @forelse ($tasks as $task)
            <tr>
                <td><strong>{{ $task->step->name }}</strong></td>
                <td>{{ $task->instance->workflow->name ?? '—' }}</td>
                <td>
                    <a href="{{ route('documents.show', $task->instance->document) }}" class="text-decoration-none">
                        {{ $task->instance->document->title ?? '—' }}</a>
                </td>
                <td class="small">{{ $task->due_at?->format('d/m/Y') }}</td>
                <td><span class="badge bg-{{ $task->status === 'pending' ? 'warning' : 'success' }}">{{ $task->status }}</span></td>
                <td>
                    @if ($task->status === 'pending')
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('tasks.decide', $task) }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button class="btn btn-sm btn-success">Valider</button>
                            </form>
                            <form method="POST" action="{{ route('tasks.decide', $task) }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="decision" value="reject">
                                <input type="text" name="comment" class="form-control form-control-sm" placeholder="Motif obligatoire" required>
                                <button class="btn btn-sm btn-outline-danger">Rejeter</button>
                            </form>
                        </div>
                    @else
                        <span class="small text-muted">{{ $task->decision_comment }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Aucune tâche.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
