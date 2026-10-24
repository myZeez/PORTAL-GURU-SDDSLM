<td class="px-2 py-2 text-center">
    @if ($result['value'] === null)
        <span class="text-xs text-gray-400 dark:text-gray-500" title="{{ $indicator->getNotApplicableReason() }}">Tidak berlaku</span>
    @else
        <span class="font-bold {{ $this->toneFor($result['value']) }}">{{ rtrim(rtrim(number_format($result['value'], 1, ',', ''), '0'), ',') }}%</span>
        <span class="block text-[0.65rem] text-gray-500 dark:text-gray-400" title="{{ $result['full'] }} penuh dari {{ $result['counted'] }} data yang dihitung">{{ $result['full'] }}/{{ $result['counted'] }}</span>
    @endif
</td>
