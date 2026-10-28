<x-auth.login-shell
    title="University Offices Login"
    portal="University Offices"
    :action="route('office.login.submit')"
    other-text="Planning Office staff?"
    other-label="Sign in here"
    :other-url="route('planning_office.login')" />