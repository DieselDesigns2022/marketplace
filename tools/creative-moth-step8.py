import os, sys, json, gzip, base64, hashlib, shutil, subprocess, re, secrets, difflib
from pathlib import Path
from datetime import datetime, timezone
PAYLOAD='H4sIAC/v4GoC/9V9C3vbNrLoX2H8eUuxpSRSb8mRfZzEbfOdJM2Jnd7bY3t1QBKy2UiiIlJOvIn3t995AHzLdrLde7+b7SYiAQwGg8FgZjAYftkT63X7ebRKNtFiITdx+zhYhqvsRWt9vd6bGF/2PDmPNhJ+7nVET7qdbs93hkPRCXzp+uPBfDjsD8ZDMer0O12nN5/70uk40h/6YtgLOqNBd+y7YiQ6bmfPNvbEPJEbBCZ7fdHtDvreHJr0R0GnP+yPvd7Y8cdB0O+MAscdeIE7lL7sDjv9YNwNOvN+R448p9vtjXsIzL8WqysZA7jzc3fg2gb9tWeU/phx5IdiMfNhcHKVtG5C+cmcHpptgUNuc2lTlZr2xWrv8vIOcS1R6JQqPud691FqtV0s8mPtid7cE6OgKx0BQxq7ruPLntcbOUDGQeC5o2HfG3c743EgukEQjDzRGYyA1sJ1A384prFytwju6RH0eLFaiaWM18KXxvF6fXGRw/TgYrWN09cbeXHxq1ysocAQsfFrvvRUbm5CX8bwKz849VrVfBEtRbg6+ezLdRJGK/X27HoTfRLeAmvNw5VYGP5CxLGxg0oXqy8XK5yN9dZbhL4x3658hGaEq0B+blgT4yYKA66iauKfXyeTjfy4DTeS+POt3CzDOIZ2jdpZtQ6yptdSBHLTMJ8L/1o2FSYTY70Jb0QibWMVNeMEqGMbS/G5Ka7k1Cm034+ZCtOV/GTUkadhHewLH0BMG+EqsQBVoMumYZ2bYWBe5iHJzQZqmebBPnDIcnqeLwznRmN/dnry7veTd+fmu5P/en9yejZ7fXL2628vzMvpdGq+/e30zLTyVPk2yizFCkZXGJvuWC7XyS11f3r68rc35+bMjzdz89Iyvn41noTxLE424eoKaiASafHREbK4ZSESwos2SaPnjsvwoexGbsL57XNo0yiXJpvb8og0UmlvgjgEuzNNCynhbyRMHdBCz03zkF81eCJsblnuC//IRSzvB74Umw9VMqdzCGNB6k7n4QKW9exGbFJQqkjTxf755auzk3ez349fvXxxfHYye/nmzD43I1o9MUiecxMmbLZB4QVPzuVlHcIpb+iOp9O5gEFYRoIrz0CuLK3MhvlydSMWYWB83MqtNFTLlrkLfkZGHLwmYoMn3UrH90HeKjpVy+JEJNtYF2tk6zq8ozm4D/vn11EENYTBg+AZqscemGsO4uYamH3rgwCLTdvkNWqoRaposF0HwB/BTigbGcAS8pPGju3AOtjIZLtZlVrfGb5I/OtGaQyw1IGD1HrfB7peyeQ1YAfrD6UFrX6m3MFdESCDS4UqAyI4s0V01SgNbmKYrTJ4gK/kzNm1HrfhR9tFAKIuMTxpxOIGKGG8XUiBEwErUFwB9i1zJ2a5n/vJ7VpOc/P/ywlMP75Uk38QzhsgMmKZNOqk5WRy9sfbk9NzgnNpWQwPZGJe4hIzVTrJ8dhargIo0r2tZmKzESDBuAqsMl3BNoNoJeEfsViYl3ay2UroU3WQ1sp3/nG69Gbx1oPOG9D/slFG46MaqGU7ttt3CntFmMhlPCVkQDQstjJu8AOLi0a20qimWmqWjQjhbrji6gDImnhRtJgeNphAIJjMr1/3Q01qeEEF1g8/6FFjHRwlV9PEwor8m6p+VJBwjDCsdQQ4INAwWQDUlokMBc+BTESIBNv/aD3REscqDDWJErGYAmOtkgaPGzhvDSwYT2Ejbbg27Ya+DBcNrtvu9CxVZQorrMGV7VxlTWEsQCK7xS5xJ4HdvWHmV+gsVdhgv0UsQJQyyeMFbs6Mms29NV3rx07PBkRsoiNWvodJuRLUIVLbmqbwrPgMeAEePkI9HCBVxB82jYAq8hh5ROrRNml94iP9sE1cdfiI/9qmL1aveaueHsKQ4ZF29p37ebptwCqF//a0zqqVunZJMVDvazR7MZRz1EgDCWq4LwRo9l1Q3N3eqOP2O/PO2JOu7I/cwbg/GgtXBv1530E9XPqDodMfFzT7zmg0GA4HctR3hl0hnSFo90O3MwJV15FD0PY70pODYTDsdMZd4Xd8x/eCuRxLaNgTwqlo9qjYd1CvV6oj0CFOjLcn716/JK3ldHpeqwzaOwgHogF2Di8Sm0BX3Ej8EZcf0wao2KWl/JCWAckBK0Gbu65SeJd1K+PwapWDlL1I66w3UbD1k7RK+pzWCNezTRh/0BX0Y1oO3cqraBPKFEbuTVYr2q5zCOvHtDzaBDlE1VPWeguq83JWrFR8mY06+rRaRCLIhiRulzAd1ecMPO7KWQX9KII/oQtuEW3zDTZyDksKhJVuch0tJa65ynOe0MuoMGm5N7la4T9krgY9paVL3nvT8vR5GQENUFW1zU8CRGwYpzyZPqdQ0jfy8xp0aXgjQakAthXLtQD+SMGXX6cQ5lIGnvBTnkifVQ20N3JGbSog6kTgw+bswB8Mus5cCHfkDwNYweNexxv0+53xPBjCGh6BzAhc+DHudodB4PYE1HDGsjdwAmc08B5lzmocq7bsC5EIDxUYMGZfPHu0jXqPlar6KpuoLGdoQ0AJs45glq6uE3QdvEGTUKJda5ym70HeiAUKb/MX0O0D4zfQMkB/ME7xrW16Ikm4+Bn/gpm/lhJ3D/MtMpbxv+gR2TuOiROwRP+2zavwRopP4pZ60L8z3sfX7/Rv4HjAKcX35AY14rbB/fyaFmlDVBnFmVUuSKnGzdlQVgFqJcY+M5TSC3bY7PvrdMuJyXiu34cKJiFaOk/yDcGyEyttkdTb+mijNhRGxg8/GI9vr3dQq2Lu3WOdlMyLrC8jjA1lipfsjLt0f64lsg87kFIyjX0wsmz9m7SO9AFVtPRpu1mkv1HDTx8WwpNZkS8I66wq6XWgAaZvwiVOJKizE4PUpso0st1jsPkHSgoiWNKLWHvER8LRBOTwCXE0ETl8ICTpaUYo4jvG1VRI4huNr1ZAsSH9gg1uqXQo+pFTxnK6vrbAwZpmxX8mkO9JcvGzdztDCaNeXh4UZ6bskyIdPWP+nTTaT65DYDW1WlTlql1wXvQG4WKcvngGxmcESm0sF2B/GjCsTRID3jaMiv4N45lcofQKjDlsTcYVSpVZxFJlRlBiA0TIRhphMHVLziugkpxyX8aRQRJJCSSUR6m+S/UaXM8ywK7crmL/WgZb6LZom11H61hjHadoB60wsINWEMbrhWAawyM51kAR317BgwCuF5vZWiTX8MQOmwAHGLRoQ52RGISBJbNwZRfXY9wCKqyBMqEfAgDA3o6xMzI/ZrANYHEEMxXDe6UrzaAUuLMISH6GHTZuKKxdpiiL5Aw+EtRbG38CQF2myAzWM/CFJ2G0U2/dUoVhoOgPr7QuBy+nSBQDtwB4z+xqAMTGxR66NG7kxZ59gSrucg3MdrFncVVZrIrQtmuqGsN2LgP6qQGA5ALjl9EoDpQGliqWRsCjIZ3V2OIgti3CEN/MtPTWfxZynnCDIrPhxhYbMTaP60cat4hS+M4twmQKBWp00wtUQTbRDQ6IWm5zJWp09N6PsFPYJwrsdLEHg396iP+U/TnYqHaatQ5trBUyO2ZrXYejVeyGtFvDuzVKnBwGhfWHqpPwr2F/onWDusp+UNltlIwrCYjU8xeAZVvifnTN6lbnl1MT1zI6g1bbpQejQUNSJA0yqrF57UoxL9uu49gdq2X+zYjmc2hOfRWWD7ueEXnziP5pfgoDaU7UugeZpKlqAhyjwYLGbLHggW3Q3DUizbblobBWZOQW4y4QValRhpXTpwyoAdhCnRI0ls1QWwlx2o0zNW8CYwnYlW/nlD9bkTWVdljcJgZtm62N+ASbn1z5oP8TqhnnAorUNuMabHmK5bTiQNzaVQ+l+VpKImulVyA6rMbnCA3WjPE6Sq6fGCef1wsEmFzLcGME4VWYgMrCzB4Tj4MSHBhgPW1AyfmcGL5uD7P5J8xryziFuVbtCfmJaYdLgAoDMo2LreN4QwOpwMTmMeUkvHYG1voQaQKZU0BfK3rvpH1ubtd+tOQdfQFIpX67yroBXWSqXHXX7AW+Fp3+APAi6OQF490UCfUVnaX8Xu2tOBmO7Q7qzkTU1gncn6JTeyqwg39wQUJ/gGHLnKDHtRmwIRizGVC0DS62HcftkWcWiW6ouu3akdi03JWmUMctxDGneVgoiOihwChGAYknxgsUE6BD0WEcalU4Q9kegnWvgA0BRHBLENW+AHXQ5s6znYEnCTIBRgXtouJ0V2cANdRMBea5uRCgh13X21GoaxNroN2zRHQ3OyqmxcBLIk6aPrpI66uqScBqBlW7JHntAcW0brvzXIg4hmoCw2QdIX8DdyZREi61epXjPli878+em9YhFVs/jQY9x7HQ4gQhv5W7jmsewXHwA7HR7KY187aHWps8KmzroDPaFdQUk+HzLhbDP3WDPjJfEQmvxcqXTwxABvcGEihFqhPbqL05v7uVuDRzj/E86d0MVSR/sQ1ws4CtG929K/QHBEETLD+b+BVZ0ZNJAkyNlIB922AfF6416Aq4Gn10oNWIbQKmHiABQ7htGT8XBSTjMKk9Piod4dw9Zn/Rtr2asZyxXxYNOcu/nTos21n1WgHBhv5uCWG+Fh/UOo1gqqJ5jXDwYYf3NuS8fGKcwuhRI00iA9RQJkw9nnRedxMmRNMV6QdXGzCLJrt2g3TJV4wKMCmU1Ysqn/K5+9vNBjXMDbBAYGfbaEGVL1hF36BPo0IewTYnE9L5MjUP9TqWBtVdaA17D9hYXuHQxWSQsBgI0SYoV6SNQa3CCMzLSb5lWTkBNWIKq5ZG1YzmTZimZroeKpoGAmIdowxHGdbTJZ0v5nusFWkmkwkF5Qvd28RQqhmXwewCbVGEPDGeX0v/A7EQ7+pKK6D5JzMCi3L8NKnlSUUx6JO0Hdq70s1HA2HaaVSIhE+M38M4THL9o4uXNigaLuEKmNxECUsdQq4eh3T2EQ08QFUdwRQbnzYgK0CH3K5hPUhePsDZ20XC8PUAuaQo0dKB1HYbyLkAMMUuF6AMbte40yXiA8IA5WYtC8qdGi52vtQke2y/d4/Sg5nzaDthJZj3FmL51JdpY2F2hKj20bQaOYKI8Qs6L8so9Q5gKBbdISX2Sc+vuEsy54iNgT7oGFDPqUjImwkxbAUk9Xf4S2gbp6q4c/Mvsr4UUFwuj7Pp2JO500rZscXXTwI1zhkiNAe1eq8+q9aYZ7RQqm7H0u5mu9aWUHNXdEQ/2q6p6RXNANtkE0wTsW7hpbrno42cdNGjiYLKBS74vMUH5ki4EF64CJNbGzrHSAfloKWDJlvtT9giEwoG4Gt8izVz7/6lXM0zcpTyZgZAQYtmP9gGKt6AiYvOA8X/zLRFT/VsKVZbsOCq29Gyuh3VshADQB5aKmMWf+SxU6/UMubfGabqTQ5fPbVvF2IFVqsyWQiq8ufCGn9N3S5ugcoSBJMMDNWlZjQODVNybLNF9xIfOBlrUEyAlVu7yM72Wclnsnsu4pYeLHnYlYsHfiifMfoT2WFsb1vIdUbeX1w7KYyAEefcZWX/mgLq3arpii2N9/l+nNGfIqtgxuJa1xFNKDb/gX5aJFs4wiYFhgUqQuuyxlCOp7U1S9InZ3wp7SBzqWf+9JInnY2keSgXIAQZOj/hePTP+incrmgMNKiDyokDj/t+Dz3Fq+VOp2pPUSKbauiYtF3HVTU+fPY7lM+nMocFgK6EGilnBZ5K6S6fOt8QrVcX7rY/Rz1nikcWtRzCqBdCimhWMnbJTzuGBMEPjFBjuFR44AG0Dwd35cM4qnIv/mfQe7qu4ecqMhbR6goklVyEVyGI/JbxTs5BVbomDYXGWRwgLldPXoWrwmlgTXAoVpSfpd8wQ2AesDZAu0FpATMc1a/PRmHd86rXrGCoGK0j0PvVNIIN4FimfY4UqqrRQIO6E5sczPskBSsdOXymR7RpcZDerk4pXJVsB+g1F2X6BKYxHci9B5ZLCYxpiAU7bjioBk1jmCuc+Wx2lOgFuuPKUVGBtc4xQkWbEIhJEtUaEtl8qUjEHTJUajpOjzQlp+rfn1xbiR4oU6IHCFdDTKQgLEn4PxpgtBqPrpbYbcP8o7lsBsavk3ASm9aETgQL9XjZqIK6aajnvhq2E1uMUimwHa9pm7qYaat2Fq/EGhSXJMeG9D/NfkUE+Z8JbMFoc5HGrIKdaYWmO3cF8bvqCgIrZxkmhbWmIlmNcuxpOKe9dB1EDQtEzOoMjP6Yx9OwLF4Li8Uz4X8AcMyF+/Lg7l6prYK1c3JbRz2u1tvkXxLQ5fBUglhWdFiZY5/YtBTjqRooYnKgZx4+KkO72uTVJdUyNbzrm6SqUqWj8k6D+uN5FvuRi/fIx3iknvLHh1drwY09tKrGEBMJg0ZxR+OwUbAAVIF16I4d2ur0OGtqasPusOc49++EJyt01AmDYBsNsH5heWEPILM2MOEgEPiUVOvKqkrPBsj5StWBaGRwdqzDvkNYPwG19GrGPhHzn39vN44m56L5j+PmfzvN8ax5+VP7yPoRXl5cHOXeT3/4WwvKrKP9f76AVUgQEVoZGIWpfo0pPuirWIdfdQic1Wh/BZBf961/hhrAvXR5T/OllhFYPWjlJ9dGvIUFC/u88uqic3OXq6ikT6BMnO4MBtgm/kvkz0Y901aMgGKY7r+wl9dIUzZcGt9uR6WuQe+2LGBTIUvQlG+cpsHmOJU0DoWlTFUPANUesQ+DyoWWf/sWcbGnBgaaihpHzrqDlnpbIAiXdfjtFv9/tfTPxyP/jrG07brY7YdjDQN/MPT6A3cwHIzFwJGu9Dvz7jDwRccJuu5o0Akczxt2e2KEccbufBB0e+6oHwyHw3kwDupjDe+/HYfejNVUR+arvatxpM0KmhDYq9Sz4m3WmblMGzLmmwi0m3I0jSps0IKH+TwLl/LlcrlNkPoNBmHnC/8b9n8QBXheZMFkgMqEb/+Bb6vVjpdgWPui/UImmyhMTGyhogLM18aftvGHcTUJjWPjjAQDOgOPDi9WT6/dw2KA29M2vIKC9eFbNuHJMI9t2DfWt+x3Ub4TdeACIvgDKGciIZ+pJ+WKWuCNEzIU2MoJIhnTfRQSamgY6EWCMYd0utN62l5TzxyrOb3YA+Ig2x+qkMv0NJ4OcaTYoE64Aiz4CAJ0Ta6HeOBLFcvB59mEbP6timPAU8yQPA+p9cIN2N+tTpwCsPB9MGtekPbqyQUwPnLTiYCRwqzieZ7GH3kN+KLB0f7AMUeHT4PwJh0VUAGkrkGlF3sGLCiJkSeAeQJjfXo0/RUkim4NbdvQ+JChoskyPzBo5nB2jaVMrqMAmoNkxPgZ4ll4rL3UBBU0Dugkgs4ADMXkHT7PKQXGU2XooLkPdfEdIsYX2VgwTTEQ5/B4sdDqxNM2FytElanKegy7TFFjVyGATJMSvHTgaKrCCPcMeKMvw+DLozQGBU8Yjw4zUjFQJFUBCaCWwgNJ9rTNzeEHjzkd/CmfF5WGzeYJzUh+RJn9j977iHhduwIwbpdY32jDnp5eQ4LXSCkGKGPlQXkEPWjUmhLppZ9dtGBY300FOgAVvMJBsWAP3VNSATRJPgIqlen6yJO1FJ9B37oCZfliz+07SLesB2+bJIDQz3QpiiXC07Z6aTwVxjUot7u59vCdBOH3tC0QVBv5ngXUUxDF0eqKxk73cGh4/I5PgnBVk5dELc38Miz20rzahGpFFBk4dcbthxiLig6WCR/TwfAxHv39ZtGwWsrfgpGwl2AEgKycqlepyt+CNXOxuthrYesDkhBcg5wBl3QAwS/SK198mm8RvNY0bZ81U7G1eEKAjnSUh0WolpIWdEgEoi0vAIwSCTKhUBXB6XTTgj4vIHoJHKBDhHAuuCx/Nqos2uLimKTLB+etKDoZBIcAX/LySLlEo4KXkNEpihNQasAsyQojtJh5C7H6gLJWLkgAk0W9wWGFyysj3vjfAlYskjz/K0qw/YiUWERXEVT7FAa8GBxYDMa1xI0rfYyTW5L6kYfRV815mExwFsAKgEI0GTBM42JvIf5xSytJHCK//1tIgAYB4Uy9wDwosUEXpxtVjkxD4nhaqqzyJmIhgjCN7RpHA8pADnJuE7vuHO4kJGgiHbXQi1XyjA/1JkZaXsP/1i7m0lce1TAqnejio8N61OvmA1f2txEeWhr30PiIT9xgrdCNQhLgQg+IxesbPK5QcjpEVSD2myivm7QUCZmKzC6TmtATsEoXt1WxTUoBtOYnqAqUFU0ScTWdgSoBeiK+SyV8iuoLunKNB90fyhgD4e5DOKXr96NZ6IGxRESqWJ5ur64kCSrtdXiayM8woVKk6CLYHL5ojkNBv4Cgxh3rEhdpKN+Oe7E7Rl4hl8e/xOEVCaxZ/TXG6gV04LVj7aijH1o+eP5YXhyFAyG8n41XJyNQohIpUCvFqGQT2u5Y9imi6d1XRq2o0+Le8AilFgEyMykqXodBIJGKSm2h7BUVrvIpPQXR8/7m3H+uPR5CPaJbUNN2rz06mHlM78opXoTExwMMKctCcfgIpLRWW1Azd27YWtWdqBeqB8Vx9zZ9x+YumHmk7gEI5DplGoIKoACm7KsUuyqvtJXqkvJNUaHFGmgg5bjqibqeXrG8lIIDG5R2gpKWCAZKAn9Jgy/txy0MjOULOBRDqILL0BxEC1YEQPIkjKFYcFRqa5eNthI30CwUTVrvgMF/kTFMl8RTywLXAb45dEuKzpHmmeskWc+8bbgIZkDMzW3j/NEX1fMX0ZuuUgvQtKflSptJEWvjLcZS4dRiE3iO5ulTDI9FnJ/y+38z4j+liL8BEVqD9NM2UPoewYKqvwpRK/ICDq1B9/B3p505MnHL1nZWvF3C+r89PA4CQ3vnbUM75+3MdEqd9GCTqDakNWBD3CCQB+fhZglsVAiiU24T7hy4LnPYi/gDmA1J5kvhOtglOUDwt0iRwtcaLQ7KFEvtovj/Q87y8PJuirMa90TeU1xjrT94gkK2XbLTBE+0Aa7ZpOZoiZJz7HZPKJMpufx+F8UZHZAUrXFS38qW99ghHYRvpVY3Hx5C/rCLJ6bc39vy0UOp68wVD91R0P91tAjkBrmHDydKiPWdRyFWOVHbgV4uBklHFmlXXJHJsAY655qLyBeLjNlyBwh1mzSjUz34qEfneUVP1LzJBUUNMU8WPCHL0eWwhEDxuPB+HZIES15W5DVDtbm2lYzJ76WZQaO0W73sQeVMtQXlpEyfeVODpUXiYxHSMlXChWzbw1aNf+WLivKb4MMB/tWEjRreJBLqLbbLVTzZyDWs+kbHBhGEqWIc251vLOvgSqwn7mj9+a5V47X4ApWbZHBPnAOMKpwvok9NjN+diNUtRQ7UtjOIV+zaIk3pLwwX7Pa/HXjR52Yc/gNUoolHsXlNeFMPmWYnHbAH7PfhADaBK0A0iWAonR1DQdG1/JKviYP+D9gmQtHAjHWMzrDjrD9bX2qpXE9YIOPdHfrHblmXiv1NuE7gVxD5W4yPbNEWfUoSKNocLxYN8zy1Qy5NqwWYnaBQTc9GmL+sL/xvSwQBxfi+CmElrjAHnw9C5INpi/h2lTtSsb5wigUKFZum3cPKP1lQpOaz25eBAt5CDGKZtMiKOuCGyhW6q6VZZWHTOkg2t18E5tyAxXkTXgkYYwvwW3NCmE8b4OEzmPIGYdXiE50Dbt1CXlAu8qkJ1leIN/4P7vgIjR31X7jdHHCKG9YBP7E4b9TDwd4MvWe0DDzzJcMOBAldtwgk5eWAfu6sgzs8tYHZ05NWe8rGGWqqeX76o07QHQXDYR92XHfoBmO/13P6jjfoO4E/7vZdfzToj4cjZ9TFo7TOQA77o/l84I89zx0EhTw/Prx153PhjofBoDfqdMZDt4OpOgHw0HWHw3ln4IzGw16/4zr9nt8bOL1xrz8fzbvd7sgbl/P8dFzb6GCen0x5m28Xi2MK+6AUEkqRV6Ir1cBwd8YRg5yOFspzQnQAje0kgHmmxiqlBezJbynPi0ST5JirHWaq2eNUIiLwg4rMX60H4S3BGSHwYFv0YVcNRaYSRgFz97xzFCH5eKUDpFkGC2dhxtTLAXWVNpSHnKtJ0+BaRybB07oQWRCgkkcrvG51hNHeHBnOmpLxMwBQE6X3tYIqz+2sydEDw9+JcsFgQEcDLk8QK0ALeQVvDzE2YrsQm1x+D0SFC0tHAlmVd/IKZB0m1Ivz6U8I0ccROdfd+WWdJpKDqjXRLEooK7T38YpfEnM8UIn8h6n/5nxXUtxSlhOlU/1+Xz6tna3Y8DIaYhFHSsARJMu8PM+hDEpNcXTFqc+pxliUzZhymWlDzktwWdDt1/yCrzgWUt2novnkUiSxNKUrNYB9dv9uBntGjWB1e3M57g6GsjMez8eDQVeK0Vh0HWfcGfldp9PpuQPP9YKuL8becOCBGJ4PAxCNTgdkYs8vCNY5yN+eGAzmc9+RYzEce6LfGQ+63lyIoD8euE5HSM9xfDHoeP0+QBa9kex3RF+MXEe6FcHaG4Fkxb8KqZHPdyVANkrTCW921b28pHTJUKE7GNgG/ZV2sqMNsEtttraU/oFK8NRehld8bSxud5zOYOY6s85wVgqp4aDi+OPi/kAS1+v1hT8f9YYDvzvqy/Ec6NvtOl7f7c67sOn1Rt6oK/qwSY0CR0CVwPcddzwXPa8zEvNSIMnzdyfHZyfG2fGzVyc7QlsbF6t8lKrx+/G7578ev2uAaWYZb9+9fH387g/jP0/+wEsx6qbiyZv3rxvluHLLePPbmfHm/atXxouTn4/fvzozsirQVscfv3xzZrx/c/rylzcnL6pNHKyq3LkGZqU9e/n6hKqkBd6t8ezlLwhGv1bJU7EJVj89O379tgr6+ft3707enM2yKr+9Md6/xU6qZRcrC4YJvZxMX65W0YtnGRggzunJ2XSbzEdLr2c8/+3VKwChn2dbkP1RIGd+CBrQffRXd2aQ/mGgh5QjuHH8/uy32cs3AOM14Gbn5onCHvREdZ2M9FiJgxML01gozmzWtFLfKVXKWZLVWdDHDGcn//us0CqLaEtnqKb0G2fp/8JMcLjbd81EZcXkR8yakVou6UVCnfE1DZSuNKIIuzoSFoPu0o47/b71V5Aam+Jw68hDIy1EBIaB9RfNDRkHHD3apjzrdTZB1x+PJGwonaET9PvzoRh2Ak+6zsjzu7LTHYJa74x9AXuPO5fDQIyE3xkMfDHypDMYO4WtayAHnaFwYfvyg8FgDFbFOJh3MY39IHB6Pia6H89HQ2gs5w7sgYEbBJ4773c86MPpi/LWBVsn7Cr4197+JtpCL5iqOiGH3i8nZ3gLDZOjwwa0czM7v7go58cvZbvPSkA5R2UCoBG1+Dq12pVApCeljInkxD+D9w/HMY7Hg5HX8ftA5F4/EL1O3+2LThetJaCjPx/DTjQczN1BH1WD/tD3hNfxfG8gAtAlhn59HKNyFxmz2YuX72azltm+5lDGdj7E9+0m9OGfZ1GUgI4q1oit+ci8io/4SgDWJ0rUN6lP/odhlqSaxlMn/V2JueScg9GHYpI7iyLuGj8oANYEbw1QyOoTqGtl4dTvtit0+WXx1Kr9wU8/qbYHGP+4Pw8/J9uNrHSfXiRDG0VfVWD9U91VKER/5sPA23/HuPHZ5U/7bQwVJwiFUO8KbultMIWNQW2y8O26G47XAEy5dfj60f+YrX3V7n/UzUMuL9zR2SebQ5Wcmz/jiMzyNwk4lzLIo5nKqUWtbD1+jv1XEN5sMWkK2t1/nJyahaIXfMOd7grhosBCoOtMRbnEjbTmyWdgTrw7igk5ZuHK59u6nOfxgSa/nLw5eQdi8IVp1V6x5vDBtN0ZueyrFwVmKpfGpzBRIYu2KVfbZcOsXq3MzfbFXvvvWO0C/jTMBl4m+Lt5of5cftW/WtaPltm+2LNV+PmyEl3OtD0nSl9OKU93jFnuJYx5ee5eFgO39ScNCnwH1vjXQPrhUiy+zheRgKcINgD51QsTzCpEucOtcldODnAtWNQDvyLDfr2VYrMbkImKetNx4T/DcSb0n1kDPE1u/mccrcwqmC935VaVKmqdmMWLomqO42matMo2eenCWNaNLN+6XtywlPkn2CW4fGJcOnbK/UB5xfHpXYf6SP/80mvAb4UHLGAdwm+2qjjNw8Wi4dgqm7rqyTaPgOEwh5pdyCifYqICtzPR1eCk1JQLHTNYuLapsl2aaMlSFiTKHoxvbujNf8jPAlNetEKWO6g4RZyflp1n+UybKkMHMeCuPjtZnypO+zjfK8dkN8U9/epLK9/cdbfS9bOarr1/R9e9rGt2dxRIzR6S76d1djH73K3Pgntpn/e+oUQnhKbTznO6NZJzxFg7eJuQnGUVZ+xjaqgcknauBBVYKq1ewOnR1ZtylwXapteW8pysemEWK+SHAGrp4wvKMnWcOsaJommKCuQBTGEoioRWNwB0Yu9irg7o+kHkOnnkulXkCKln9bh49biU+kyTLObokctdyS90Gl70bGNtWni6MwWhNPZgA6ophqWHM8yqtYhiFKWY2Toyjl/iGcTDqHTKqHRqUHlWg4q3axq+AZu6ZLh5Iuks6TPOXsz1m6p+E3cpRkKlzpoe1t0Xtgvp2ew0SdojamP1XPoY/MzPfQOgBKuIv86iumOqK/lAuVptmk+YEMDDLuX1VPxXnd1qspw8PYtjsU2VdIcuP4AFZHBuGQaa/3AWWQZ04wyvK8TT6gdPOESLnvQXPHjv1J/woBg5ghAAsWRQ0dMxcxtfXZuvak0F1va1oYAHgFATkLrb+RGfL9ykgV50W5sN6ktAd4+wMO4ydBvYFQY2Fb8Agwl51GblixVG8WDEqL4LxbFyVmoa0W2YuPIZGdfCmJNccvpJB9Xh96vy7SZDfub7SwSzcjk/zZCbu49fSNRbSpukkFJqSy1SIAx+DjfAGRVUluID3fynseu7VuYDwqb7GLn3vEbY+N8vbB41yNdg3YTrRY6GQURBWcGWvwEijVyofB3xs1xnBerrs7E89TvfwxIuskQWyUiX4O5nhx0Y1fFDpx4C58N5cDzdx3KTmx8Bc466LvjAUGoQSbNMl9HgZO1VBDIUi2YoJ1xyLnOfTQL7p+U4fzMtlUYzJ6wx9SQY7Cvzm+DxRm3lh49wicEkKjteFn6ah6y+xJWDyUop6Hj4qYRKKRmbEpFZSdgrFHFxqV1C57A09NcLFPXZFFGXWRXxdwk8Stbj2gWW1N5a3CfRRMl/T0HJQzWDOVFYhNjbBVHPZc1UwlhzC2hH0DJSqDayWjEihsvTIQ8iHaNzpLAs60LjEba2D3IXvNL4euUdUcBFArPhbYlnwAyP+UN0DxC4Qo70kAhpfJog2yiSbuSfHKD4GKqmYNx/hbAayn20BWn6jnz3Rur9ifXx1i4KI/Gmmniqtb+QAha9ThlJ1xvoFlAOBuFQzujDEuhHy6jP5sPHGaZ1bqb4PmdntXGNx85UblyHKGJvv422nb8etTdRE+9o6xjhbDuiut/IT+PxuLBk368+rECW0aXKIjs9UrcguZbK4Hw6fvc7WJ0R65CkanIOqmZ6i/u7cazsdzVo5qasdlFUOJwj/xWTGKB73IDkxvsyy3W0EfAqXDHmlL6wHt3aL4Eg6pmWPnUrOtvO7W0f9Oep0ibUB/1Km3LxW4asls9RQce5CasXVdWW/V4lUFeB7VcS0dQp0Sk21fwXukpTIevu0kzECDm9sf+NJExtwumR/hJLmnSKaGmf32P8Ncn4+3Fk2fdZiN0B2IeXj9Q5vn8GQGd+hekQ6MNXQPNb1h0wcXf8veT5DpK4eqy7x1MZ/oNjAxP05POaYshpUDjlMWYI54wQJXOikB06b9amJsTP6tQl/TaXtiMUjKaXFpQ9dXYpsTM5QrIje47YY6Old1mLV/4DNHns0s/M7PAG4M1aDpIHbPiGbdzGl601xY/scGX+FXpuzdc6rPRtCLuaj4G1Re2Fh3PfV1Lv42s1AVZJ1eDX6sY91tuZNLsykwpbzMWA0QEYNKLykeJRc0Hj0L3oPIo6P7I74SzfnQwcvVAaQUAZRXyWSPGuvacmTXFZmOeSG0+3iT/DpQWzu1w3rHtt8+9bZuzM0WL1N7ykVMwuzZmSOQ1yQZDsc/DNtHQ9Bh1w2f2bdNE9T28+5Utzdz+gjrpGQnEd2S0MKKCTJtdpdkZnbgePmfIfFDN1nu7scpW+VkUHf7vUC5WhzrXVQJR10qSLWmhLauNEf4q84KQ38Z5bPGm35U24yDn7qy9yYcSU318lCGu7na6Zfq0B9I5oRaHzGN4oAnVHifOOqeNI4MxgqpA9wIfS5ZkpVT94YLw9G5uSXheLuWxrYw8WD8w3G68FDeour9tmQBTRMi4sq7H5+Kfd+Y5Rjy3cuUGmzObbcAfqZBE2AXXbh3NMom+DzGHKlnS/0sNdvXzow833iaSUa62qGyMHXq0jzomc3Qc0bsI4c32VNdw8AJK9JHqKRq5SPu/7xqLFH0LceRJk2couzRvgGwpnBqkSFFwKj+wo97FcK/0quzoJo5xMq4iWUpPfLSmTPQeNFwVkIDEjP/PJjiMoJfzUKcy0x5m9C8dR6VWmwvHYxd6/ZGYomokF2ZbaxvJuUSflaOniUP7a07SevWNMeMb2ANvTJcQpWSHqa9cH6afYq99H3/9I7+/55rervuztpp+f15+TP8cEOPr6LX8yFFGbnZ7QZ5thj8ARg7C+59C6eEwK7E93KnJ6GUnzyOPIjYZ1oL8MA2ogScCG+XAKOswJep0sF1OAg5IT/QecxLBWHcKqtvnDIjngw8YfrpIDPn7Ed+3sJbE/FtC3bQ0Zw/5UWlJ1kHcdabIfDvmJc8VxdhXk1O36QaClOyWm2tiyFYkJ6DgNPcxdbD4w+nzmCdNSXzuqr4XJNbA7bsKxdug4xI8zpdRI2QQPXA7+X8xnOc3C7lEVb/dkEnTDpGPNDxqxVeDfkkx6fvruZ+U0VUNOow6nsNVIRpTHAFtQYbS50MX2jshFHvbOIWadgeSQy3Vy27i4SFfiuVpVlxZ++qYy6lxj89fJhDP3P6dbTDyvulipCLFB4gs/54SDTqIPcqUGvRI3D472oasX9wwTasD4KMGE+gSZaUwPMQR09ZibDzB6mEu52uJHZ/JAdnX4LRNH+2ZlwhDdHTdx7sHxVbgEIRw0qYaxECSsDfxKOuXV4YA9wlr615Fxsff2+PR0YqigR6N0W4ffUkoxihm++z+gLcnEHYwAAA=='
from pathlib import Path
from datetime import datetime, timezone
import base64, gzip, json, hashlib, subprocess, shutil, secrets, re, os, sys, difflib

ROOT=Path('/var/www/marketplace.dieseldesigns.co')

def run(args, **kwargs):
    return subprocess.run(args,check=True,**kwargs)

def note(value):
    print(value,flush=True)

def main():
    os.chdir(ROOT)
    if subprocess.check_output(['git','status','--porcelain'],text=True).strip():
        raise RuntimeError('Working tree is not clean. No site files changed.')
    head=subprocess.check_output(['git','rev-parse','--short','HEAD'],text=True).strip()
    if head!='717605c':
        raise RuntimeError('Expected inspected commit 717605c. No site files changed.')
    manifest=json.loads(gzip.decompress(base64.b64decode(PAYLOAD)))
    originals={}; candidates={}
    for name,change in manifest.items():
        path=Path(name)
        if path.is_absolute() or '..' in path.parts:
            raise RuntimeError('Invalid patch path.')
        if change['before'] is None:
            if path.exists(): raise RuntimeError('Unexpected existing file: '+name)
            data=change['content'].encode()
        else:
            original=path.read_bytes()
            if hashlib.sha256(original).hexdigest()!=change['before']:
                raise RuntimeError('File differs from inspected source: '+name)
            originals[name]=original
            lines=original.decode().splitlines(keepends=True)
            for start,end,value in reversed(change['changes']):
                lines[start:end]=value.splitlines(keepends=True)
            data=''.join(lines).encode()
        if hashlib.sha256(data).hexdigest()!=change['after']:
            raise RuntimeError('Candidate verification failed: '+name)
        candidates[name]=data
    backup=ROOT/'backups'/('step8-social-content-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S-%f'))
    stage=backup/'candidate'
    stage.mkdir(parents=True)
    note('BACKUP / STAGING: '+str(backup))
    tracked=subprocess.check_output(['git','ls-files','-z'],text=True).split('\0')
    for name in tracked:
        path=Path(name)
        if name and path.suffix in ('.php','.sql') and path.parts[0] in ('app','public','tests','database'):
            target=stage/name;target.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(path,target)
    for name,data in candidates.items():
        target=stage/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
    note('STAGED PHP SYNTAX CHECKS')
    for name in candidates:
        if name.endswith('.php'):run(['php','-l',str(stage/name)])
    env_text=Path('.env').read_text()
    match=re.search(r'(?m)^\s*(?:export\s+)?DB_NAME\s*=\s*(.*?)\s*$',env_text)
    if not match:raise RuntimeError('DB_NAME not found.')
    raw=match.group(1).strip()
    if raw.startswith(('"',"'")):
        closing=raw.find(raw[0],1)
        if closing<1:raise RuntimeError('DB_NAME quoting needs verification.')
        db=raw[1:closing]
    else:db=raw.split('#',1)[0].strip()
    if not re.fullmatch(r'[A-Za-z0-9_]+',db):raise RuntimeError('Invalid DB_NAME.')
    mysql=['mysql','--protocol=socket','-u','root','--batch','--skip-column-names']
    socket=subprocess.check_output(mysql+['-e','SELECT @@socket;'],text=True).strip()
    if not socket or ';' in socket or '\n' in socket:raise RuntimeError('MySQL socket needs verification.')
    tables=subprocess.check_output(mysql+[db,'-e',"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE';"],text=True).splitlines()
    for table in ('social_content_states','social_content_manual','social_content_audits'):
        if table in tables:raise RuntimeError('Queue table already exists: '+table)
    migration=candidates['database/migrations/2026_10_27_social_content_queue.sql'].decode()
    test_db='cm_sale_test_'+secrets.token_hex(10)
    created=False
    try:
        note('ISOLATED SOCIAL CONTENT TESTS — schema only; no live records copied')
        run(mysql+['-e','CREATE DATABASE `'+test_db+'` CHARACTER SET utf8mb4;'])
        created=True
        env=os.environ.copy();env['CM_SALE_TEST_DSN']='mysql:unix_socket='+socket+';dbname='+test_db
        statements=[]
        for table in tables:
            if not re.fullmatch(r'[A-Za-z0-9_]+',table):raise RuntimeError('Invalid table identifier.')
            statements.append('CREATE TABLE `'+test_db+'`.`'+table+'` LIKE `'+db+'`.`'+table+'`;')
        run(mysql,input='\n'.join(statements),text=True)
        run(mysql+[test_db],input=migration,text=True)
        run(['php',str(stage/'tests/SocialContentQueueTest.php')],env=env)
    finally:
        if created:run(mysql+['-e','DROP DATABASE `'+test_db+'`;'])
    note('ALL PRE-INSTALL CHECKS PASSED — backing up changed files')
    for name,original in originals.items():
        if Path(name).read_bytes()!=original:raise RuntimeError('Source changed during tests: '+name)
        target=backup/'original'/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(name,target)
    with (backup/'before-schema.sql').open('wb') as output:
        run(['mysqldump','--protocol=socket','-u','root','--no-data',db],stdout=output)
    review=[]
    for name,data in candidates.items():
        before=originals.get(name,b'').decode().splitlines(keepends=True)
        review.extend(difflib.unified_diff(before,data.decode().splitlines(keepends=True),fromfile='a/'+name,tofile='b/'+name))
    (backup/'review.diff').write_text(''.join(review))
    installed=[]
    try:
        run(mysql+[db],input=migration,text=True)
        late=['app/Controllers/AdminController.php','app/Views/partials/dashboard_nav.php','public/index.php']
        order=[n for n in candidates if n not in late]+late
        for name in order:
            if name in originals and Path(name).read_bytes()!=originals[name]:raise RuntimeError('Source changed before installation: '+name)
            if name not in originals and Path(name).exists():raise RuntimeError('File appeared before installation: '+name)
            path=Path(name);path.parent.mkdir(parents=True,exist_ok=True)
            installed.append(name);shutil.copy2(stage/name,path)
        run(['git','diff','--check'])
        for name,data in candidates.items():
            if Path(name).read_bytes()!=data:raise RuntimeError('Installed candidate mismatch: '+name)
    except Exception:
        for name in reversed(installed):
            original=backup/'original'/name
            if original.exists():shutil.copy2(original,name)
            else:Path(name).unlink(missing_ok=True)
        note('Site files restored. New queue tables retained for inspection; do not rerun without checking their state.')
        raise
    note('INSTALLED: Social Content queue, View/Manage permissions, Marketplace menu and limited-admin redirect.')
    note('No account permissions were changed. Open /admin/social-content to review. Sale settings remain unchanged.')
    note('BACKUP: '+str(backup))
    note('REVIEW DIFF: '+str(backup/'review.diff'))
    run(['git','diff','--stat'])
    run(['git','status','--short'])
    note('RESULT=0')

if __name__=='__main__':
    try:main()
    except Exception as error:
        print('STOPPED: '+str(error),file=sys.stderr,flush=True)
        print('RESULT=1',file=sys.stderr,flush=True)
        sys.exit(1)
