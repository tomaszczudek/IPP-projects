# class Main : Object {
#     run
#     [ |
#         x := self fo: 9 fo: 3.
#         _ := (x asString) print.
#     ]

#     fo:fo:[:x :y|
#         _ := x divBy: y.
#     ]
# }




Executing block...
Array
(
    [0] => Array
        (
            [var] => x
            [expression] => Array
                (
                    [type] => message_send
                    [selector] => fo:fo:
                    [receiver] => Array
                        (
                            [type] => variable
                            [name] => self
                        )

                    [arguments] => Array
                        (
                            [0] => Array
                                (
                                    [type] => literal
                                    [class] => Integer
                                    [value] => 3
                                )

                            [1] => Array
                                (
                                    [type] => literal
                                    [class] => Integer
                                    [value] => 9
                                )

                        )

                )

        )

    [1] => Array
        (
            [var] => _
            [expression] => Array
                (
                    [type] => message_send
                    [selector] => print
                    [receiver] => Array
                        (
                            [type] => message_send
                            [selector] => asString
                            [receiver] => Array
                                (
                                    [type] => variable
                                    [name] => x
                                )

                            [arguments] => Array
                                (
                                )

                        )

                    [arguments] => Array
                        (
                        )

                )

        )

)
Array
(
)
Executing block...
Array
(
    [0] => Array
        (
            [var] => _
            [expression] => Array
                (
                    [type] => message_send
                    [selector] => divBy:
                    [receiver] => Array
                        (
                            [type] => variable
                            [name] => x
                        )

                    [arguments] => Array
                        (
                            [0] => Array
                                (
                                    [type] => variable
                                    [name] => y
                                )

                        )

                )

        )

)
Array
(
    [0] => Array
        (
            [name] => x
            [order] => 0
        )

    [1] => Array
        (
            [name] => y
            [order] => 1
        )

)
0