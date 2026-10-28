<x-auth.login-shell
    title="Planning Office Login"
    portal="Planning Office"
    :action="route('planning_office.login.submit')"
    other-text="University Offices?"
    other-label="Sign in here"
    :other-url="route('office.login')" />