@extends('layouts.app')

@section('title', 'Nuevo reactivo')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Nuevo reactivo</h1>

    <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-2xl"
         x-data="{
            type: '{{ old('question_type', 'multiple_choice') }}',
            options: ['', ''],
            pairs: [{ concept: '', definition: '' }, { concept: '', definition: '' }],
            addOption() { this.options.push(''); },
            removeOption(i) { if (this.options.length > 2) this.options.splice(i, 1); },
            addPair() { this.pairs.push({ concept: '', definition: '' }); },
            removePair(i) { if (this.pairs.length > 2) this.pairs.splice(i, 1); },
         }">
        <form method="POST" action="{{ route('questions.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Título (interno, para identificarlo en el banco)</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="150"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Enunciado (lo que ve el alumno)</label>
                <textarea name="stem" required rows="3"
                          class="w-full px-3 py-2 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('stem') }}</textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Materia (opcional)</label>
                    <input type="text" name="subject_tag" value="{{ old('subject_tag') }}" maxlength="100"
                           class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Puntos</label>
                    <input type="number" step="0.01" min="0" name="default_points" value="{{ old('default_points', 1) }}" required
                           class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Tiempo (segundos)</label>
                    <input type="number" min="1" name="default_time_seconds" value="{{ old('default_time_seconds', 60) }}" required
                           class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
            </div>

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Tipo de reactivo</label>
                <select name="question_type" x-model="type" required
                        class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                    <option value="multiple_choice">Opción múltiple</option>
                    <option value="matching">Relación de columnas</option>
                    <option value="open">Pregunta abierta</option>
                    <option value="code">Código</option>
                </select>
            </div>

            {{-- Opción múltiple --}}
            <div x-show="type === 'multiple_choice'" class="space-y-3 border-t border-slate-200 pt-4">
                <p class="text-[0.8125rem] font-medium text-slate-800">Opciones (marca la correcta)</p>
                <template x-for="(option, i) in options" :key="i">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="correct_option_index" :value="i" :checked="i === 0">
                        <input type="text" :name="'options[' + i + ']'" x-model="options[i]"
                               class="flex-1 h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech"
                               :placeholder="'Opción ' + (i + 1)">
                        <button type="button" @click="removeOption(i)" class="text-acode-danger text-xs">Quitar</button>
                    </div>
                </template>
                <button type="button" @click="addOption()" class="text-acode-tech text-sm font-medium">+ Agregar opción</button>
            </div>

            {{-- Relación de columnas --}}
            <div x-show="type === 'matching'" class="space-y-3 border-t border-slate-200 pt-4">
                <p class="text-[0.8125rem] font-medium text-slate-800">Pares concepto / definición</p>
                <template x-for="(pair, i) in pairs" :key="i">
                    <div class="flex items-center gap-2">
                        <input type="text" :name="'concepts[' + i + ']'" x-model="pair.concept" placeholder="Concepto"
                               class="flex-1 h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <span class="text-slate-400">—</span>
                        <input type="text" :name="'definitions[' + i + ']'" x-model="pair.definition" placeholder="Definición"
                               class="flex-1 h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <button type="button" @click="removePair(i)" class="text-acode-danger text-xs">Quitar</button>
                    </div>
                </template>
                <button type="button" @click="addPair()" class="text-acode-tech text-sm font-medium">+ Agregar par</button>
                <p class="text-xs text-slate-500">La columna derecha se mostrará barajada al alumno automáticamente.</p>
            </div>

            {{-- Abierta --}}
            <div x-show="type === 'open'" class="space-y-3 border-t border-slate-200 pt-4">
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Respuesta de referencia</label>
                    <textarea name="reference_answer" rows="3"
                              class="w-full px-3 py-2 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('reference_answer') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Se compara contra la respuesta del alumno con el evaluador (IA simulada en demo).</p>
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Rúbrica (opcional)</label>
                    <textarea name="evaluation_rubric" rows="2"
                              class="w-full px-3 py-2 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('evaluation_rubric') }}</textarea>
                </div>
            </div>

            {{-- Código --}}
            <div x-show="type === 'code'" class="space-y-3 border-t border-slate-200 pt-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Lenguaje</label>
                        <input type="text" name="programming_language" value="{{ old('programming_language', 'PHP') }}" maxlength="40"
                               class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                    </div>
                    <label class="flex items-center gap-2 mt-6 text-sm text-slate-600">
                        <input type="checkbox" name="is_find_error" value="1">
                        Es de "encontrar el error"
                    </label>
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Código inicial (opcional)</label>
                    <textarea name="starter_code" rows="3" class="w-full px-3 py-2 rounded-md border border-slate-300 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('starter_code') }}</textarea>
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Instrucciones</label>
                    <textarea name="instructions" rows="2" class="w-full px-3 py-2 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('instructions') }}</textarea>
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Solución de referencia</label>
                    <textarea name="reference_solution" rows="3" class="w-full px-3 py-2 rounded-md border border-slate-300 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('reference_solution') }}</textarea>
                </div>
            </div>

            <button type="submit" class="h-10 px-4 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
                Guardar reactivo
            </button>
        </form>
    </div>
@endsection
