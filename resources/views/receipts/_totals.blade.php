<table class="totals">
    @foreach ($r['totals'] as $row)
        <tr class="{{ ! empty($row['strong']) ? 'strong' : '' }}">
            <td>{{ $row['label'] }}</td>
            <td class="right num">{{ $row['value'] }}</td>
        </tr>
    @endforeach
</table>
