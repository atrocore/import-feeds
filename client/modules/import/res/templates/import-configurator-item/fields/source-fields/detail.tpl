<div class="unused-columns-container" data-name="{{name}}">
    {{#each selected}}
        <span class="text-muted">{{./this}}</span>
    {{/each}}
</div>

<style>
        .unused-columns-container {
            display: flex; flex-flow: row wrap
        }

        .unused-columns-container > .text-muted {
            flex-basis: 33%;
        }

        @media (max-width: 1200px) {
                .unused-columns-container > .text-muted {
                        flex-basis: 50%;
                }
        }
</style>