<x-auth.login-shell
    title="University Offices Login"
    portal="University Offices"
    heading="Plan, request and track campus events."
    blurb="Sign in to submit event requests, follow their approval status, and view the university calendar of approved events."
    :action="route('office.login.submit')"
    :points="['Submit and monitor your event requests', 'Track each request until it is approved', 'View the university-wide calendar']"
    other-text="Planning Office staff?"
    other-label="Sign in here"
    :other-url="route('planning_office.login')" />
