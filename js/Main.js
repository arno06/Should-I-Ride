"use strict";

class Tools
{
    static getDuration(pValue)
    {
        if(pValue === 0)
            return "D&eacute;part";

        if(pValue<60)
        {
            return Math.ceil(pValue)+" sec";
        }
        pValue /= 60;
        return Math.ceil(pValue)+" min";
    }
}

Template.FUNCTIONS.getDuration = Tools.getDuration;

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
        this.serviceEntryPoint = "services/";
    }

    index(pParameters)
    {
        this.addContent("from_location", pParameters.from_location);
        this.addContent("to_location", pParameters.to_location);
        Request.load(this.serviceEntryPoint).onComplete(this.serviceCompletedHandler.bind(this));
    }

    serviceCompletedHandler(e)
    {
        this.addContent('points', e.currentTarget.responseJSON.interest_points);
        this.dispatchEvent(new Event(FwJs.lib.events.RENDER));
    }
}

FwJs.newController(Home);
FwJs.newController(Route);

window.addEventListener('load', FwJs.start);