"use strict";

class Home extends FwJs.lib.DefaultController
{
    constructor()
    {
        super();
    }

    index()
    {
        console.log("index");
        this.addEventListener(FwJs.lib.events.RENDER_COMPLETE, this.indexRenderedHandler.bind(this));
        this.dispatchEvent(new Event(FwJs.lib.events.RENDER));
    }

    indexRenderedHandler()
    {
        console.log("indexRenderedHandler");
        document.querySelector('form').addEventListener('submit', this.indexFormSubmitted.bind(this));
    }

    indexFormSubmitted(e)
    {
        console.log("indexFormSubmitted");
        console.log(this);
        e.preventDefault();

        let from_location = document.querySelector('#from_location').value||'Issy les moulineaux, France';
        let to_location = document.querySelector('#to_location').value||'Groslay, France';

        window.location.hash = FwJs.lib.tools.rewriteHash('route', {"from_location":from_location, "to_location":to_location});
    }
}

class Route extends FwJs.lib.DefaultController
{
    constructor()
    {
        super();
    }

    index(pParameters)
    {
        console.log("route detected");
        this.addContent("from_location", pParameters.from_location);
        this.addContent("to_location", pParameters.to_location);
        this.dispatchEvent(new Event(FwJs.lib.events.RENDER));
    }
}

FwJs.newController(Home);
FwJs.newController(Route);

window.addEventListener('load', FwJs.start);