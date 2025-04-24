@foreach($data as $projectTypeName => $projectType)
    <h2>{{ $projectTypeName }}</h2>
    @foreach($projectType['projects'] as $project)
        <h4>{{ $project['project_title'] }}</h4>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Enrollment</th>
                    @foreach($projectType['components'] as $component)
                        @foreach($component['sub_components'] as $sub)
                            <th>{{ $sub['title'] }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($project['grades'] as $student)
                    <tr>
                        <td>{{ $student['name'] }}</td>
                        <td>{{ $student['enrollment'] }}</td>
                        @foreach($projectType['components'] as $component)
                            @foreach($component['sub_components'] as $sub)
                                <td>
                                    {{ $student[$component['title']][$sub['title']]['marks'] ?? '-' }}
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endforeach
