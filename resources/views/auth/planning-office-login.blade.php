<x-auth.login-shell
    title="Planning Office Login"
    portal="Planning Office"
    heading="One calendar for every PSU campus."
    blurb="Sign in to review venue requests, manage university events and venues, and oversee the calendar of every PSU campus."
    :action="route('planning_office.login.submit')"
    :points="['Approve or reject event requests from every office', 'Resolve schedule conflicts before they happen', 'Manage venues and see all approved events in one place']"
    other-text="University Offices?"
    other-label="Sign in here"
    :other-url="route('office.login')" />
