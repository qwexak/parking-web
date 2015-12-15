/**
 * Created by svk on 15.12.2015.
 */


$(document).ready(function () {
    $('#events').multiselect({
        buttonWidth: '100%',
        nonSelectedText: 'Тип события',
        selectAllText: 'Выбрать все',
        nSelectedText: ' выбранно',
        enableFiltering: true,
        enableCaseInsensitiveFiltering: true,
        includeSelectAllOption: true, //выбрать все
        selectAllJustVisible: true, //выбрать только то что отфильтровано
        maxHeight: 400,
        onDropdownHide: function (event) {
            var events = [];
            $('#events option:selected').map(function (a, item) {
                events.push(item.value);
            });
            $.post("/getEvents", {events: events}, function (data) {
                console.dir(data);
                var resault_body = $('#resault-body');
                resault_body.html('');
                $.each(data, function () {
                    resault_body.append('<tr><th>' + this.datetimeevent + '</th><td><a href="/stats/event/' + this.ideventname + '">' + this.type + '</a></td><td>' + this.station + '</td><td><a href="/stats/source/' + this.sourceid + '">' + this.source + '</a></td><td>' + this.cardtype + '</td><td>' + this.zone + '</td></tr>');
                });
            }, "json");

        },
    })
    ;
})
;

//includeSelectAllOption


