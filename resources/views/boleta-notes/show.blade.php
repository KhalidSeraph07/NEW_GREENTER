@extends('layout')
@section('title', 'Nota / RC ficticio · H5-B')
@section('milestone', 'H5-B')
@section('content')
<h1>{{ $rc ? $record->name : $record->series.'-'.$record->number }}</h1>
<p>Estado: <strong>{{ $record->state }}</strong>. Sin CDR oficial.</p>
@if(!$rc)
<p>Tipo {{ $record->document_type }} / motivo {{ $record->reason_code }} · {{ $record->emission_date }} · {{ App\Invoices\InvoiceInput::decimal($record->snapshot['total_cents']) }} PEN</p>
<p>Original: B000-{{ $record->snapshot['original']['number'] }}. {{ $record->snapshot['reason'] }}. Otorgamiento exclusivamente ficticio.</p>
@if($member)<p>Resultado de comunicación: <a href="{{ route('receipt-notes.show',['kind'=>'rc','id'=>$member->summary_id]) }}">Consultar RC de esta nota</a></p>@else<p>Nota preparada; aún no incluida en RC.</p>@endif
@else
<p>Fecha de notas: {{ $rc->reference_date }}. Generación RC: {{ $rc->issue_date }}.</p>
<p>Ticket ficticio: {{ $rc->ticket ?? 'No recibido: no permite recuperar por UUID ni reenviar ante incertidumbre.' }}</p>
<ul>@foreach($rc->snapshot['members'] as $m)<li><a href="{{ route('receipt-notes.show',['kind'=>'note','id'=>$m['id']]) }}">{{ $m['document_type'] }} {{ $m['series'] }}-{{ $m['number'] }}</a> → {{ $m['original_type'] }} {{ $m['original_number'] }}</li>@endforeach</ul>
<form method="post" action="{{ route('receipt-notes.process',$rc->id) }}">@csrf
<label for="scenario">Escenario ficticio (vacío usa el valor normal de la acción)</label><select id="scenario" name="scenario"><option value="">Normal</option>@foreach(array_unique(array_merge(App\BoletaNotes\LocalSummarySimulator::SEND,App\BoletaNotes\LocalSummarySimulator::POLL)) as $s)<option>{{ $s }}</option>@endforeach</select>
<button name="action" value="send">Simular envío RC</button><button name="action" value="poll">Consultar ticket ficticio</button><button name="action" value="recover">Rearmar consulta con ticket conservado</button>
</form>
@if($rc->result)<pre>{{ json_encode($rc->result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
<h2>Historial</h2><ul>@foreach($events as $e)<li>{{ $e->created_at }} · {{ $e->kind }} @if($e->attention) · Requiere revisión @endif</li>@endforeach</ul>
@endif
<p>@foreach(['xml','zip','certificate'] as $file)<a href="{{ route('receipt-notes.download',[$kind,$record->id,$file]) }}">{{ $file }}</a> · @endforeach</p>
@endsection
